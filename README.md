SkyDriveAPI
===========

Microsoft SkyDrive PHP API

LOAD
===

```php
try {
    $SkyDrive = new SkyDriveAPI\SkyDriveAPI([
        'client_id' => 'XXXXX',
        'client_secret' => 'XXXXXXXX'
    ]);
} catch (Exception $e) {
    die($e->getMessage().'<br /><br />You can to try authenticate again <a href="'.SkyDrive::uri().'">here</a>');
}
```

FUNCTIONS
===

```php
# Get current account info
try {
    $account = $SkyDrive->me();
} catch (Exception $e) {
    echo 'Sorry but account info is not available now. Error: '.$e->getMessage();
}

print_r($account);

# Get quota info
try {
    $quota = $SkyDrive->me('quota');
} catch (Exception $e) {
    echo 'Sorry but quota info is not available now. Error: '.$e->getMessage();
}

print_r($quota);

# Get permissions info
try {
    $permissions = $SkyDrive->me('permissions');
} catch (Exception $e) {
    echo 'Sorry but permissions info is not available now. Error: '.$e->getMessage();
}

print_r($permissions);

$folder = isset($_GET['folder_id']) ? $_GET['folder_id'] : '';
$file = isset($_GET['file_id']) ? $_GET['file_id'] : '';

# Get folder contents
# Returns array with 'location', 'folders' and 'files' list
try {
    $contents = $SkyDrive->folderContents($folder);
} catch (Exception $e) {
    echo 'Sorry but this folder seems not be available. Error: '.$e->getMessage();
}

print_r($contents);

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
        $SkyDrive->putFile(__FILE__, uniqid().'.txt', $folder);
    } catch (Exception $e) {
        echo 'Sorry but this file couldn\'t be uploaded. Error: '.$e->getMessage();
    }

    echo 'Done!';
}

if (isset($_GET['create'])) {
    # Create a new folder
    try {
        $SkyDrive->newFolder($folder, uniqid());
    } catch (Exception $e) {
        echo 'Sorry but this folder couldn\'t be created. Error: '.$e->getMessage();
    }

    echo 'Done!';
}
```

I will add more features :)