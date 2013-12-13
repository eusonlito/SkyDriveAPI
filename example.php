<?php
include 'SkyDrive.php';

try {
    $SkyDrive = new SkyDrive([
        'client_id' => 'XXXXXX',
        'client_secret' => 'XXXXXX'
    ]);
} catch (Exception $e) {
    die($e->getMessage().'<br /><br />You can to try authenticate again <a href="'.SkyDrive::uri().'">here</a>');
}

# Get current account info
try {
    $account = $SkyDrive->accountInfo();
} catch (Exception $e) {
    echo 'Sorry but account info is not available now. Error: '.$e->getMessage();
}

$folder = isset($_GET['folder_id']) ? $_GET['folder_id'] : '';
$file = isset($_GET['file_id']) ? $_GET['file_id'] : '';

# Get folder contents
# Returns array with 'location', 'folders' and 'files' list
try {
    $contents = $SkyDrive->folderContents($folder);
} catch (Exception $e) {
    echo 'Sorry but this folder seems not be available. Error: '.$e->getMessage();
}

if ($file) {
    # Download file
    try {
        $file = $SkyDrive->getFile($file);
    } catch (Exception $e) {
        echo 'Sorry but this file couldn\'t be downloaded. Error: '.$e->getMessage();
    }
}

if (isset($_GET['upload'])) {
    # Upload file
    try {
        $SkyDrive->putFile(__FILE__, basename(__FILE__), $folder);
    } catch (Exception $e) {
        echo 'Sorry but this file couldn\'t be uploaded. Error: '.$e->getMessage();
    }
}
