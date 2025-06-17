<?php
namespace App\UseCases;

use DB;

class EmpresaUseCase extends CsvChunkReader
{

    public $colunas = [
        'cnpj_basico',
        'razao_social',
        'natureza_juridica',
        'qualificacao_responsavel',
        'capital_social',
        'porte',
        'ente_federativo',
    ];
    public function __invoke($file)
    {
        foreach ($this->readCsv($file, $this->colunas) as $chunk) {
            DB::table('empresa')->upsert($chunk, ['cnpj_basico'], $this->colunas);
        }

    }

}
