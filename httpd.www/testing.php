<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/PHPWord/src/PhpWord/Autoloader.php';
\PhpOffice\PhpWord\Autoloader::register();

use PhpOffice\PhpWord\TemplateProcessor;

// Ladda enkel mall
$templateProcessor = new TemplateProcessor('test.docx');

// WordML-sträng
$wordml = 
  '<w:r><w:t>E: </w:t></w:r>'.
  '<w:r><w:rPr><w:b/></w:rPr><w:t>E</w:t></w:r>'.
  '<w:r><w:t>xtraversion</w:t></w:r>';

// Sätt in med setValue(..., true)
$templateProcessor->setValue('Energy', $wordml, true);

// Spara
$templateProcessor->saveAs('test_output.docx');
echo "Klar!";
