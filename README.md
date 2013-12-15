# SkyDriveAPI

Microsoft SkyDrive PHP API.

Remeber to create your authorized application in https://account.live.com/developers/applications

Once created, configure this API with the Client ID and Client Secret values.

If you are using a multi-subdomain environment, configure your application with first level domain, for example:

http://user1.mydomain.com/, http://user2.mydomain.com/, ... > Redirect URI = http://mydomain.com/

## LOAD

```php
include 'SkyDriveAPI.php';

try {
    $SkyDrive = new SkyDriveAPI\SkyDriveAPI([
        'client_id' => 'XXXXX',
        'client_secret' => 'XXXXXXXX'
    ]);
} catch (Exception $e) {
    die(
        '<p>'.$e->getMessage().'</p>'.
        '<p>You can to try to authenticate again <a href="'.SkyDriveAPI\SkyDriveAPI::uri().'">here</a></p>'
    );
}
```

## FUNCTIONS

### me()

```php
# Get current account info
$account = $SkyDrive->me();
```

### me('quota')

```php
# Get quota info
$quota = $SkyDrive->me('quota');
```

### me('permissions')

```php
# Get permissions info
$permissions = $SkyDrive->me('permissions');
```

### folderContents($folder_id)

```php
# Get folder contents
# Returns array with 'location', 'folders' and 'files' list
$contents = $SkyDrive->folderContents($folder_id);
```

### createFolder($folder_id, $name)

```php
# Create a new folder
$folder = $SkyDrive->createFolder($folder_id, $name);
```

### uploadFile($local_file_path, $name, $folder_id)

```php
# Upload file (returns array info with new file information)
$file = $SkyDrive->uploadFile(__FILE__, uniqid().'.txt', $folder_id);
```

### downloadFile($file_id)

```php
# Download file (returns file contents)
$contents = $SkyDrive->downloadFile($file_id);
```

### delete($file_id | $folder_id)

```php
# Delete file / folder
$SkyDrive->delete($file_id);
```

### copy($file_id)

```php
# Copy file into another folder
$SkyDrive->copy($file_id, $folder_id);
```

### move($file_id | $folder_id)

```php
# Move file / folder into another folder
$SkyDrive->move($file_id, $folder_id);
```

I will add more features :)