<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:10240', 'extensions:xlsx,xls,ods,csv'],
            'estricto' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Elige un archivo para importar.',
            'archivo.file' => 'El archivo no se subió correctamente. Inténtalo de nuevo.',
            'archivo.max' => 'El archivo pesa más de 10 MB. Divídelo en partes más pequeñas.',
            'archivo.extensions' => 'Formato no soportado. Usa .xlsx, .xls, .ods o .csv.',
        ];
    }
}
