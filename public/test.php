<?php
echo "PHP is working!<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "Current Directory: " . __DIR__ . "<br>";
phpinfo();
