<?php
if (!defined('ABSPATH')) exit;

/**
 * Extractores de texto según tipo de archivo.
 * Devuelven mensajes "no disponible" si faltan librerías, sin romper el flujo.
 */
class AICode_Extractors {

    public static function extract_text($file_path, $mime) {
        $mime = strtolower((string)$mime);

        // Imagen → OCR (Tesseract)
        if (strpos($mime, 'image/') === 0) {
            return self::extract_from_image($file_path);
        }

        // PDF
        if ($mime === 'application/pdf') {
            return self::extract_from_pdf($file_path);
        }

        // Word
        if (in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword'
        ], true)) {
            return self::extract_from_doc($file_path);
        }

        // Excel
        if (in_array($mime, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroenabled.12'
        ], true)) {
            return self::extract_from_spreadsheet($file_path);
        }

        // PowerPoint
        if (in_array($mime, [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ], true)) {
            return self::extract_from_presentation($file_path);
        }

        return "El archivo ($mime) no se pudo procesar para extracción de texto.";
    }

    private static function extract_from_image($file_path) {
        if (class_exists('\\thiagoalessio\\TesseractOCR\\TesseractOCR')) {
            try {
                $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($file_path);
                return $ocr->run();
            } catch (\Throwable $e) {
                return 'OCR no disponible: ' . $e->getMessage();
            }
        }
        return 'OCR no disponible (Tesseract no cargado).';
    }

    private static function extract_from_pdf($file_path) {
        if (class_exists('\\Smalot\\PdfParser\\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf    = $parser->parseFile($file_path);
                return $pdf->getText();
            } catch (\Throwable $e) {
                return 'PDF Parser no disponible: ' . $e->getMessage();
            }
        }
        return 'PDF Parser no disponible (Smalot\\PdfParser no cargado).';
    }

    private static function extract_from_doc($file_path) {
        if (class_exists('\\PhpOffice\\PhpWord\\IOFactory')) {
            try {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($file_path);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $el) {
                        if (method_exists($el, 'getText')) {
                            $text .= $el->getText() . "\n";
                        }
                    }
                }
                return $text;
            } catch (\Throwable $e) {
                return 'PhpWord no disponible: ' . $e->getMessage();
            }
        }
        return 'PhpWord no disponible.';
    }

    private static function extract_from_spreadsheet($file_path) {
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
                $text = '';
                foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                    foreach ($ws->getRowIterator() as $row) {
                        $cellIterator = $row->getCellIterator();
                        $cellIterator->setIterateOnlyExistingCells(false);
                        foreach ($cellIterator as $cell) {
                            $text .= (string)$cell->getValue() . "\t";
                        }
                        $text .= "\n";
                    }
                }
                return $text;
            } catch (\Throwable $e) {
                return 'PhpSpreadsheet no disponible: ' . $e->getMessage();
            }
        }
        return 'PhpSpreadsheet no disponible.';
    }

    private static function extract_from_presentation($file_path) {
        if (class_exists('\\PhpOffice\\PhpPresentation\\IOFactory')) {
            try {
                $presentation = \PhpOffice\PhpPresentation\IOFactory::load($file_path);
                $text = '';
                foreach ($presentation->getAllSlides() as $slide) {
                    foreach ($slide->getShapeCollection() as $shape) {
                        if (method_exists($shape, 'getText')) {
                            $text .= $shape->getText() . "\n";
                        }
                    }
                }
                return $text;
            } catch (\Throwable $e) {
                return 'PhpPresentation no disponible: ' . $e->getMessage();
            }
        }
        return 'PhpPresentation no disponible.';
    }
}
