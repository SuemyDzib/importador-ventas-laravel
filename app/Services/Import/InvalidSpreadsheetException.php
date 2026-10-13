<?php

namespace App\Services\Import;

use RuntimeException;

/** Error que impide procesar el archivo completo (vacío, sin columnas, demasiado grande...). */
class InvalidSpreadsheetException extends RuntimeException
{
}
