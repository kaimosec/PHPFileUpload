<?php
error_reporting(E_ALL & ~E_NOTICE);

//Functions
function command_exists($cmd)
{
    return !empty(`which $cmd`);
}
function lg($str)
{
    echo $str."<br>";
}
function freePermissions($filename, $verbose)
{
    $verbose("Setting file permissions to 0777");
    $chmod = chmod($filename, 0777);
    if(!$chmod) {
        lg("WARNING: Failed to set file permissions from "
            . fileperms($filename)." to 0777");
    }
}
function runCommand($cmd, $filename, $verbose)
{
    //Run command on file
    lg("Running command..");
    $cmdString = sprintf($cmd, $filename, $filename, $filename, $filename);
    $verbose("Command: ".$cmdString);
    passthru($cmdString);
}

//Input processing
$verbose =
    array_key_exists('v', $_GET)
    ? function($str) {lg("[V] ".$str);}
    : function($str){}
;
$forcePost = array_key_exists('force_post', $_GET);

//See if we can decode with base64
$canBase64Decode = function_exists('base64_decode');
if(!$canBase64Decode) {
    $verbose("Cannot use base 64: function base64_decode doesn't exist");
}

//Try to maximize upload sizes
$set = ini_set('upload_max_filesize', 0);
if($set === false) {
    $verbose("Failed to increase upload_max_filesize");
}
$set = ini_set('post_max_size', 0);
if($set === false) {
    $verbose("Failed to increase post_max_size");
}

$maxUploadSize = ini_get('upload_max_filesize');
if($maxUploadSize !== false) {
    if($maxUploadSize == 0) {
        $maxUploadSizeString = 'Unlimited';
    } else {
        $maxUploadSizeString = $maxUploadSize;
    }
} else {
    $maxUploadSizeString = "Unknown";
}

$maxPostSize = ini_get('post_max_size');
if($maxPostSize !== false) {
    if($maxPostSize == 0) {
        $maxPostSizeString = 'Unlimited';
    } else {
        $maxPostSizeString = $maxPostSize;
    }
} else {
    $maxPostSizeString = "Unknown";
}

//See if we can upload files
$canUploadFiles = true;
if(!ini_get('file_uploads')) {
    $verbose("file_uploads disabled. Attempting to enable..");
    if(ini_set('file_uploads', 'On') === false) {
        lg("WARNING: Failed to enable file_uploads. Files cannot be uploaded. Use POST");
        $canUploadFiles = false;
    }
}

//Decide whether to show post or upload page
if($forcePost || !$canUploadFiles) {
    //POST page

    $navbarHtml = "<a href='?'>Upload via File Upload</a>";
    $formHtml = "
    <label for='data'>Your file's data</label>
    <textarea name='data' id='data' placeholder=\"Your file's data\" style='width:100%;height:27vh;'></textarea><br>
    ";
} else {
    //Upload page

    $navbarHtml = "<a href='?force_post'>Upload via POST</a>";
    $formHtml = "
    <label for='file'>File</label><br>
    <input type='file' id='file' name='file'><br>
    ";
}

//Shortcut commands
$decompressArray = [
    'tar'       => ['tar', 'tar -xf %s'],
    'rar'       => ['unrar', 'unrar x %s'],
    'tar.7z'    => [['tar', '7z'], '7z x -so %s | tar xf -'],
    '7z'        => ['7z', '7z x %s'],
    'tar.bz2'   => ['tar', 'tar -xjf %s'],
    'tar.gz'    => ['tar', 'tar -xzf %s']
];

foreach($decompressArray as $key=>$val) {
    $hasAll = true;

    if(is_array($val[0])) {
        foreach($val[0] as $cmd) {
            if(!command_exists($cmd)) {
                $hasAll = false;
                break;
            }
        }
    } else {
        if(!command_exists($val[0])) {
            $hasAll = false;
        }
    }

    if(!$hasAll) {
        $verbose("Cannot extract from $key, required binaries aren't present");
    }
    $extractHtml .= "
    <button type='button' onclick=\"setCommand('".$val[1]."');\" ".($hasAll ? '':'disabled').">
        Extract $key".($hasAll ? '' : ' - UNAVAILABLE')."
    </button>
    ";
}

echo "
<html>
    <head>
        <title>Kaimo's File Upload</title>
        <script type='text/javascript'>
            function setCommand(cmd)
            {
                document.getElementById('cmd').value = cmd;
            }
        </script>
        <style type='text/css'>
            label {
                font-weight: 600;
                margin-top: 30px;
                display:inline-block;
            }
            input[type='text'], input[type='submit'] {
                width: 100%;
            }
        </style>
    </head>
    <body>
        ".$navbarHtml."
        <hr>
        Max Upload filesize: ".$maxUploadSizeString."<br>
        Max Post Size: ".$maxPostSizeString."
        <hr>
        <form method='post' enctype='multipart/form-data'>
            ".$formHtml."
            <input id='b64' type='checkbox' name='b64'".($canBase64Decode ? '' : ' disabled').">
            <label for='b64'>Base 64 decode after uploading".($canBase64Decode ? '' : '- UNAVAILABLE')."</label><br>
            <input type='checkbox' name='force_overwrite' id='force_overwrite'>
            <label for='force_overwrite' style='margin-top:0;'>Overwrite file if it already exists</label><br>
            <label for='cmd'>Command to execute after uploading and base64 decode. %s for filename.</label><br>
            <input type='text' id='cmd' name='cmd' placeholder='e.g. tar -xf %s'>
            <fieldset>
                <legend>Shortcut Commands:</legend>
                $extractHtml
            </fieldset>
            <label for='filename'>Filename</label><small> (required if uploading via POST)</small><br>
            <input type='text' id='filename' name='filename'><br><br>
            <input type='submit' name='upload' value='Upload'>
        </form>
    </body>
</html>
";

//Processing
if($_FILES['file']) {
    //Uploading via File Upload
    if(strlen($_POST['filename']) > 0) {
        $filename = $_POST['filename'];
    } else {
        $filename = $_FILES['file']['name'];
    }
    $tmpFilename = $_FILES['file']['tmp_name'];

    //See if file already exists
    if(file_exists($filename)) {
        if($_POST['force_overwrite']) {
            $verbose("File exists, overwriting");
        } else {
            lg("ERROR: File ".$filename." already exists, aborting");
            die();
        }
    }

    //See if file was successfully uploaded
    if(!is_uploaded_file($tmpFilename)) {
        lg("ERROR: Failed to upload. Did your size exceed the limits?");
        die();
    }

    //Do we need to base64 the file?
    if($_POST['b64'] && $canBase64Decode) {
        $verbose("Base64-decoding file");

        //Read data
        $verbose("Reading temp data");
        $data = file_get_contents($tmpFilename);
        if($data === false) {
            lg("ERROR: Failed to read temp file ".$tmpFilename.", I/O error?");
            die();
        }

        //Decode data
        $verbose("Base64-decoding data");
        $data = base64_decode($data);
        if($data === false) {
            lg("ERROR: Failed to base64-decode data. Invalid format?");
            die();
        }

        //Write data
        $verbose("Opening new file");
        $handle = fopen($filename, 'w');
        if(!$handle) {
            lg("ERROR: Failed to ready ".$filename." for writing. Insufficient permissions?");
            die();
        }
        $verbose("Writing to file");
        $write = fwrite($handle, $data);
        if($write === false) {
            lg("ERROR: Failed to write file data. I/O error?");
            die();
        }
        fclose($handle);
    } else {
        //Move the uploaded file
        $verbose("Moving file from temp dir");
        $move = move_uploaded_file($tmpFilename, $filename);
        if($move === false) {
            lg("ERROR: Failed to move file from ".$tmpFilename);
            die();
        }
    }

    //Free file permissions
    freePermissions($filename, $verbose);

    lg("File successfully uploaded to ".$filename);

    if(!empty($_POST['cmd'])) {
        runCommand($_POST['cmd'], $filename, $verbose);
    }
}

if(array_key_exists('data', $_POST)) {
    //Uploading via POST
    if($_POST['data'] === '') {
        lg("No post data received. Did you exceed the post size limit?");
    } else {
        $data = $_POST['data'];

        if($_POST['b64']) {
            if(!$canBase64Decode) {
                lg("ERROR: This server cannot decode base64");
                die();
            } else {
                $verbose("Decoding base-64");
                $data = base64_decode($data);
                if($data === false) {
                    lg("ERROR: Failed to base64 decode data. Out of format?");
                    die();
                }
            }
        }

        if($_POST['filename'] === '') {
            lg("WARNING: Filename omitted, defaulting to 'output'");
            $filename = 'output';
        } else {
            $filename = $_POST['filename'];
        }

        if(file_exists($filename)) {
            if ($_POST['force_overwrite']) {
                $verbose("File already exists, overwriting");
            } else {
                lg("ERROR: File already exists, aborting");
                die();
            }
        }

        //See if we have write permissions
        $verbose("Opening file handle: ".$filename);
        $handle = fopen($filename, 'w');
        if(!$handle) {
            lg("ERROR: Failed to get handle on file. Insufficient file permissions?");
            die();
        }

        //Write file data and close handle
        $verbose("Writing to file");
        $write = fwrite($handle, $data);
        if($write === false) {
            lg("ERROR: Failed to write to file. I/O error?");
            die();
        }
        fclose($handle);

        //Set file permissions
        freePermissions($filename, $verbose);

        //Execute commands
        if(!empty($_POST['cmd'])) {
            runCommand($_POST['cmd'], $filename, $verbose);
        }

        lg("File ".$filename." has been created");
    }
}
?>