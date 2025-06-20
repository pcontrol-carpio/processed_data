<?php
namespace App\UseCases;

use DB;
use Exception;

class EstabelecimentoUseCase extends CsvChunkReader
{

    public $colunas = [
        'cnpj_basico',
        'cnpj_ordem',
        'cnpj_dv',
        'matriz_filial',
        'nome_fantasia',
        'situacao_cadastral',
        'data_situacao_cadastral',
        'motivo_situacao_cadastral',
        'nome_cidade_exterior',
        'pais',
        'data_inicio_atividade',
        'cnae_fiscal_principal',
        'cnae_fiscal_secundaria',
        'tipo_logradouro',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cep',
        'uf',
        'municipio',
        'ddd_1',
        'telefone1',
        'ddd_2',
        'telefone2',
        'ddd_fax',
        'fax',
        'correio_eletronico',
        'situacao_especial',
        'data_situacao_especial',
    ];
   private function formatarData($data)
    {
        if ($data == null || $data == '') {
            return null;
        }
        return (int)substr($data, 0, 4);
    }
    private function pegarEmpresa($baseCnpj)
    {
        return collect(DB::table('empresa')
                ->where('cnpj_basico', $baseCnpj)
                ->first())->toArray();
    }


     private function pegarSimples($baseCnpj)
    {
        return collect(DB::table('simples')
                ->where('cnpj_basico', $baseCnpj)
                ->first())->toArray();
    }



    private function popularTabelaBase($idEstabelecimento, $empresa,  $simples , $linha)
    {
       $capital_social = (float)$empresa['capital_social'];
        $natureza_juridica = (int)$empresa['natureza_juridica'];
        $porte = (int)$empresa['porte'];
        $municipio = (int)$linha['municipio'];
        $matriz_filial = (int)$linha['matriz_filial'];
        $situacao_cadastral = (int)$linha['situacao_cadastral'];
        $motivo_situacao_cadastral = (int)$linha['motivo_situacao_cadastral'];
        $dados = [
            'estabelecimento_id' => $idEstabelecimento,
            //empresa
            'razao_social' => $empresa['razao_social'], //ok
            'natureza_juridica' => $natureza_juridica,
            'capital_social' => $capital_social,
            'porte' => $porte,
            //estabelecimento
            'empresa_id' => $linha['empresa_id'], //ok
            'cnpj' => $linha['cnpj'], //ok
            'nome_fantasia' => $linha['nome_fantasia'], //ok
            'cnae_fiscal_principal' => $linha['cnae_fiscal_principal'], //ok
            'uf' => $linha['uf'], //ok
            'municipio' => $municipio,
            'bairro' => $linha['bairro'], //ok
            'data_inicio_atividade' => $this->formatarData($linha['data_inicio_atividade']), //ok
            'matriz' => $matriz_filial,
            'simples' => $simples['opcao_pelo_simples'], //ok
            'mei' => $simples['opcao_pelo_mei'], //ok
            'situacao_cadastral' => $situacao_cadastral,
            'data_situacao_cadastral' => $this->formatarData($linha['data_situacao_cadastral']), //ok
            'motivo_situacao_cadastral' => $motivo_situacao_cadastral,
        ];
        $keys = array_keys($dados);

        try{
        DB::table('base')->upsert([$dados],['cnpj'],  $keys);
        } catch (Exception $e) {

            dd($e);exit;

            file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true) . PHP_EOL . print_r($dados, true));
            return false;
        }

    }

    public function __invoke($file)
    {
           $progress = DB::table('csv_progress')->where('filename', basename($file))->first();
        $startChunk = $progress->last_chunk ?? 0;
        foreach ($this->readCsv($file, $this->colunas, $startChunk) as $chunk) {
            try {

                foreach ($chunk as &$linha) {
                    $inicio = microtime(true);
                    $empresa = $this->pegarEmpresa($linha['cnpj_basico']);
                    $simples = $this->pegarSimples($linha['cnpj_basico']);
                    // Visualização: Mostra cada linha que será inserida/atualizada
                    // Verifica se a empresa existe, se não existir, pula para a próxima linha
                    $linha['empresa_id'] = ! empty($empresa) ? $empresa['id'] : null;
                    if (empty($linha['empresa_id'])) {
                        continue; // Skip if CNPJ parts are missing
                    }
                    $linha['cnpj'] = "{$linha['cnpj_basico']}{$linha['cnpj_ordem']}{$linha['cnpj_dv']}";

                    $razaoSocial = ! is_null($empresa) ? $empresa['razao_social'] : null;
                    if (empty($linha['nome_fantasia'])) {
                        $linha['nome_fantasia'] = $razaoSocial;
                    }
                    DB::table('estabelecimento')->upsert([$linha], ['cnpj_basico'], $this->colunas);
                    $registro          = DB::table('estabelecimento')->where('cnpj_basico', $linha['cnpj_basico'])->first();
                    $idEstabelecimento = $registro->id ?? null;
                    if ($idEstabelecimento) {
                        $this->popularTabelaBase($idEstabelecimento, $empresa, $simples, $linha);
                        echo '✅ Base populada com sucesso.' . PHP_EOL;
                    }else{
                        echo '❌ Erro ao inserir estabelecimento: ' . $linha['cnpj_basico'] . PHP_EOL;
                       exit;
                    }
                    $fim   = microtime(true);
                    $tempo = $fim - $inicio;
                    echo '✅ OK - Linha inserida com sucesso em ' . number_format($tempo, 2) . ' segundos.' . PHP_EOL;
                }

            } catch (Exception $e) {

                dd($e);
                file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true) . PHP_EOL . print_r($chunk, true));
                return false;
            }
        }
            return true;

    }

}
