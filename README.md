PHPFileUpload
=======
![](https://img.shields.io/badge/build-passing-brightgreen) ![](https://img.shields.io/badge/license-GPL%203-blue) ![](https://img.shields.io/badge/phpfileupload-v1.0-blue)

PHPFileUpload is a basic PHP app that may aid a pentester in uploading files to the target machine through the target's web server.

## Features
- Upload via File Upload
- Upload via POST
- Post-upload base64-decoding
- Post-upload execution (Useful for extracting packages upon upload)
- Uploading to arbitrary locations and filenames
- Automatic chmodding to 777
- A minified version of the script (phpfileupload.min.php)

## Requirements
- Target webserver must be running PHP 5.5+ (tested only on Linux)

## Usage
Upload the script to the target webserver.
Of course, you still need to find a way to upload this script first, so it's goal is instead to make all proceeding uploads more convenient while cirumventing some more common restrictions.
- Verbose mode: phpfileupload.php?v (Not available in minified version)

**WARNING:** This script contains vulnerabilities. Do not run this on your own web server.

-----

*Kaimo Sensei &lt;kaimo.sensei@protonmail.com&gt; 0xD7F9FF9ABC147E9A*

頑張れ!!
