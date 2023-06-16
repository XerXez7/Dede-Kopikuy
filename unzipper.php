
<?php

define('VERSION', '0.1.1');

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  // Periksa apakah arsip dipilih untuk dibuka ritsletingnya.
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}

if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  // File zip yang dihasilkan mis. ritsleting--2022-02-16--11-55.zip.
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}

$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);

/**
 * Buka ritsleting kelas (biar galupa)
 */
class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();

  public function __construct() {
    // Baca direktori dan pilih file .zip, .rar dan .gz. &copy; XerXez7
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);

      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => '.zip or .gz or .rar file ditemukan, siap untuk diekstraksi');
      }
      else {
        $GLOBALS['status'] = array('info' => 'No .zip or .gz or file rar ditemukan. Jadi hanya fungsi zip yang tersedia.');
      }
    }
  }


  public function prepareExtraction($archive, $destination = '') {
    
    if (empty($destination)) {
      $extpath = $this->localdir;
    }
    else {
      $extpath = $this->localdir . '/' . $destination;
      
      if (!is_dir($extpath)) {
        mkdir($extpath);
      }
    }
    // Hanya arsip lokal yang ada yang diizinkan untuk diekstraksi.
    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }
  }

  /**
   * Memeriksa ekstensi file dan memanggil fungsi ekstraktor yang sesuai.
   *
   * @param string $arsip
   * Nama arsip termasuk ekstensi file. Misalnya. arsip_saya.zip.
   * @param string $des  /**
   * Memeriksa ekstensi file dan memanggil fungsi ekstraktor yang sesuai.
   *
   * @param string $arsip
   * Nama arsip termasuk ekstensi file. Misalnya. arsip_saya.zip.
   * @param string $des
   */
  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);
    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }

  }

  /**
   * Dekompresi/ekstrak arsip zip menggunakan ZipArchive.
   *
   * @param $arsip
   * @param $tujuan
   */
  public static function extractZipArchive($archive, $destination) {
    // Check if webserver supports unzipping.
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support unzip functionality.');
      return;
    }

    $zip = new ZipArchive;

    // Check if archive is readable.
    if ($zip->open($archive) === TRUE) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Files unzipped successfully');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .zip archive.');
    }
  }

  /**
   * Dekompresi File .gz.
   *
   * @param string $arsip
   * Nama arsip termasuk ekstensi file. Misalnya. arsip_saya.zip.
   * @param string $tujuan
   * Jalur tujuan relatif
   */
  public static function extractGzipFile($archive, $destination) {
    // Periksa apakah zlib diaktifkan
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP has no zlib support enabled.');
      return;
    }

    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($destination . '/' . $filename, "w");

    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string, strlen($string));
    }
    gzclose($gzipped);
    fclose($file);

    // Periksa apakah file telah diekstraksi.
    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['status'] = array('success' => 'File unzipped successfully.');

      // Jika kita memiliki file tar.gz, mari kita ekstrak file tar tersebut.
      if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
        $phar = new PharData($destination . '/' . $filename);
        if ($phar->extractTo($destination)) {
          $GLOBALS['status'] = array('success' => 'Extracted tar.gz archive successfully.');
          // Delete .tar.
          unlink($destination . '/' . $filename);
        }
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error unzipping file.');
    }

  }

  /**
   * Dekompresi/ekstrak arsip Rar menggunakan RarArchive.
   *
   * @param string $arsip
   * Nama arsip termasuk ekstensi file. Misalnya. arsip_saya.zip.
   * @param string $tujuan
   *   Di sana
*/
  public static function extractRarArchive($archive, $destination) {
    // Periksa apakah server web mendukung unzip.
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
      return;
    }
    // Check if archive is readable.
    if ($rar = RarArchive::open($archive)) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['status'] = array('success' => 'Files extracted successfully.');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .rar archive.');
    }
  }

}


class Zipper {

  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);

    while (FALSE !== $f = readdir($handle)) {
      // Check for local/parent path or zipping file itself and skip.
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        // Remove prefix from file path before add to zip.
        $localPath = substr($filePath, $exclusiveLength);

        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        }
        elseif (is_dir($filePath)) {
          // Add sub-directory.
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }

  /**
   * Zip folder (termasuk dirinya sendiri).
   *
   * Penggunaan:
   * Zipper::zipDir('path/ke/sourceDir', 'path/to/out.zip');
   *
   * @param string $sourcePath
   * Jalur relatif direktori menjadi 
   */
   
  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];

    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);
    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    }
    else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();

    $GLOBALS['status'] = array('success' => 'Successfully created archive ' . $outZipPath);
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>File Unzipper + Zipper</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <style type="text/css">
    <!--
    body {
      font-family: Arial, sans-serif;
      line-height: 150%;
    }

    label {
      display: block;
      margin-top: 20px;
    }

    fieldset {
      border: 0;
      background-color: #EEE;
      margin: 10px 0 10px 0;
    }

    .select {
      padding: 5px;
      font-size: 110%;
    }

    .status {
      margin: 0;
      margin-bottom: 20px;
      padding: 10px;
      font-size: 80%;
      background: #EEE;
      border: 1px dotted #DDD;
    }

    .status--ERROR {
      background-color: red;
      color: white;
      font-size: 120%;
    }

    .status--SUCCESS {
      background-color: green;
      font-weight: bold;
      color: white;
      font-size: 120%
    }

    .small {
      font-size: 0.7rem;
      font-weight: normal;
    }

    .version {
      font-size: 80%;
    }

    .form-field {
      border: 1px solid #AAA;
      padding: 8px;
      width: 280px;
    }

    .info {
      margin-top: 0;
      font-size: 80%;
      color: #777;
    }

    .submit {
      background-color: #378de5;
      border: 0;
      color: #ffffff;
      font-size: 15px;
      padding: 10px 24px;
      margin: 20px 0 20px 0;
      text-decoration: none;
    }

    .submit:hover {
      background-color: #2c6db2;
      cursor: pointer;
    }
    -->
  </style>
</head>
<body>
<p class="status status--<?php echo strtoupper(key($GLOBALS['status'])); ?>">
  Status: <?php echo reset($GLOBALS['status']); ?><br/>
  <span class="small">Processing Time: <?php echo $time; ?> seconds</span>
</p>
<form action="" method="POST">
  <fieldset>
    <h1>Unzipper By Xery</h1>
    <label for="zipfile">Select .zip or .rar archive or .gz file yang ingin Anda ekstrak:</label>
    <select name="zipfile" size="1" class="select">
      <?php foreach ($unzipper->zipfiles as $zip) {
        echo "<option>$zip</option>";
      }
      ?>
    </select>
    <label for="extpath">Extraction path (optional):</label>
    <input type="text" name="extpath" class="form-field" />
    <p class="info">Masukkan jalur ekstraksi tanpa garis miring di depan atau di belakang (mis. "patch"). Jika dibiarkan kosong, direktori saat ini akan digunakan.</p>
    <input type="submit" name="dounzip" class="submit" value="Unzip Archive"/>
  </fieldset>

  <fieldset>
    <h1>Archive Zipper</h1>
    <label for="zippath">Jalur yang harus di-zip (opsional):</label>
    <input type="text" name="zippath" class="form-field" />
    <p class="info">Masukkan jalur yang akan di-zip tanpa garis miring di depan atau di belakang (mis. "zippath"). Jika dibiarkan kosong direktori saat ini akan digunakan.</p>
    <input type="submit" name="dozip" class="submit" value="Zip Archive"/>
  </fieldset>
</form>
<p class="version">Dede versi : <?php echo VERSION; ?></p>
</body>
</html>
