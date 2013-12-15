<?php
include 'SkyDriveAPI.php';

try {
    $SkyDrive = new SkyDriveAPI\SkyDriveAPI([
        'client_id' => 'XXXXXXXXXXXXXXXXXXXXX',
        'client_secret' => 'XXXXXXXXXXXXXXXXXXXXX'
    ]);
} catch (Exception $e) {
    die(
        '<p>'.$e->getMessage().'</p>'.
        '<p>You can to try to authenticate again <a href="'.SkyDriveAPI\SkyDriveAPI::uri().'">here</a></p>'
    );
}

$folder = isset($_GET['folder_id']) ? $_GET['folder_id'] : '';
$file = isset($_GET['file_id']) ? $_GET['file_id'] : '';

echo '<pre>';

# Get current account info
try {
    $account = $SkyDrive->me();
} catch (Exception $e) {
    echo '<p>Sorry but account info is not available now. Error: '.$e->getMessage().'</p>';
}

echo '<p>Your account</p>';
print_r($account);

# Get quota info
try {
    $quota = $SkyDrive->me('quota');
} catch (Exception $e) {
    echo '<p>Sorry but quota info is not available now. Error: '.$e->getMessage().'</p>';
}

echo '<p>Your quota</p>';
print_r($quota);

# Get permissions info
try {
    $permissions = $SkyDrive->me('permissions');
} catch (Exception $e) {
    echo '<p>Sorry but permissions info is not available now. Error: '.$e->getMessage().'</p>';
}

echo '<p>Your permissions</p>';
print_r($permissions);

# Get folder contents
# Returns array with 'location', 'folders' and 'files' list
try {
    $contents = $SkyDrive->folderContents($folder);
} catch (Exception $e) {
    echo '<p>Sorry but this folder seems not be available. Error: '.$e->getMessage().'</p>';
}

echo '<p>Folder contents</p>';
print_r($contents);

if (isset($_GET['uploadFile'])) {
    # Upload file
    try {
        $SkyDrive->uploadFile(__FILE__, uniqid().'.txt', $folder);
        echo '<p>File uploaded</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this file couldn\'t be uploaded. Error: '.$e->getMessage().'</p>';
    }
}

if ($file && isset($_GET['downloadFile'])) {
    # Download file
    try {
        $contents = $SkyDrive->downloadFile($file);
        echo '<p>File downloaded ('.strlen($contents).' bytes)</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this file couldn\'t be downloaded. Error: '.$e->getMessage().'</p>';
    }
}

if ($file && isset($_GET['deleteFile'])) {
    # Delete file
    try {
        $SkyDrive->delete($file);
        echo '<p>File deleted</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this file couldn\'t be deleted. Error: '.$e->getMessage().'</p>';
    }
}

if ($file && isset($_GET['copyFile'])) {
    # Copy file
    try {
        $SkyDrive->copy($file, $folder);
        echo '<p>File copied</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this file couldn\'t be copied. Error: '.$e->getMessage().'</p>';
    }
}

if ($file && isset($_GET['moveFile'])) {
    # Move file
    try {
        $SkyDrive->move($file, $folder);
        echo '<p>File moved</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this file couldn\'t be moved. Error: '.$e->getMessage().'</p>';
    }
}

if (isset($_GET['createFolder'])) {
    # Create a new folder
    try {
        $SkyDrive->createFolder($folder, uniqid());
        echo '<p>Folder created</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this folder couldn\'t be created. Error: '.$e->getMessage().'</p>';
    }
}

if ($folder && isset($_GET['deleteFolder'])) {
    # Delete a folder
    try {
        $SkyDrive->delete($folder);
        echo '<p>Folder deleted</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this folder couldn\'t be deleted. Error: '.$e->getMessage().'</p>';
    }
}

if (isset($_GET['createDeleteFolder'])) {
    # Create a new folder
    try {
        $new = $SkyDrive->createFolder($folder, uniqid());
        echo '<p>Folder created</p>';

        $SkyDrive->delete($new['id']);
        echo '<p>Folder deleted</p>';
    } catch (Exception $e) {
        echo '<p>Sorry but this folder couldn\'t be created. Error: '.$e->getMessage().'</p>';
    }
}
