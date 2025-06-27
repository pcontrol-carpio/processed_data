<?php
namespace App\UseCases;

use DB;
use Exception;

class EmpresaBaseUseCase
{
    private const CHUNK_SIZE = 30000;

    private function formatarData($data)
    {
        return empty($data) ? null : (int) substr($data, 0, 4);
    }

    private function pegarEmpresa($baseCnpj)
    {
        return (array) DB::table('empresa')
            ->where('cnpj_basico', $baseCnpj)
            ->first();
    }

    private function pegarSimples($baseCnpj)
    {
        $simples = DB::table('simples')->where('cnpj_basico', $baseCnpj)->first();
        if (! $simples) {
            return [
                'opcao_pelo_simples'      => 'N',
                'data_opcao_pelo_simples' => null,
                'data_exclusao_simples'   => null,
                'opcao_pelo_mei'          => 'N',
                'data_opcao_mei'          => null,
                'data_exclusao_mei'       => null,
            ];
        }
        return (array) $simples;
    }

    private function montarRegistro($idEstabelecimento, $empresa, $simples, $linha)
    {
        return [
            'estabelecimento_id'        => $idEstabelecimento,
            'razao_social'              => $empresa['razao_social'],
            'natureza_juridica'         => (int) $empresa['natureza_juridica'],
            'capital_social'            => (float) $empresa['capital_social'],
            'porte'                     => (int) $empresa['porte'],
            'empresa_id'                => $linha['empresa_id'],
            'cnpj'                      => "{$linha['cnpj_basico']}{$linha['cnpj_ordem']}{$linha['cnpj_dv']}",
            'nome_fantasia'             => $linha['nome_fantasia'] ?? $empresa['razao_social'],
            'cnae_fiscal_principal'     => $linha['cnae_fiscal_principal'],
            'uf'                        => $linha['uf'],
            'municipio'                 => (int) $linha['municipio'],
            'bairro'                    => $linha['bairro'],
            'data_inicio_atividade'     => $this->formatarData($linha['data_inicio_atividade']),
            'matriz'                    => (int) $linha['matriz_filial'],
            'simples'                   => $simples['opcao_pelo_simples'] === 'S' ? 1 : 0,
            'mei'                       => $simples['opcao_pelo_mei'] === 'S' ? 1 : 0,
            'situacao_cadastral'        => (int) $linha['situacao_cadastral'],
            'data_situacao_cadastral'   => $this->formatarData($linha['data_situacao_cadastral']),
            'motivo_situacao_cadastral' => (int) $linha['motivo_situacao_cadastral'],
        ];
    }

    public function __invoke()
    {
        $limit  = 30000;
        $lastId = DB::table('csv_progress')
            ->where('filename', 'EmpresaBase')
            ->value('last_chunk') ?? 0; // Valor inicial se não houver progresso salvo
        $temMais = true;

        while ($temMais) {
            echo "Lendo estabelecimentos a partir do ID: $lastId pegando de " . ($lastId + 1) . " a " . ($lastId + $limit) . "..." . PHP_EOL;
            $estabelecimentos = DB::table('estabelecimento')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $temMais   = ! $estabelecimentos->isEmpty();
            $dadosLote = [];

            foreach ($estabelecimentos as $idx => $estabelecimento) {
                echo "Lendo estabelecimento: {$estabelecimento->id}" . PHP_EOL;
                $linha   = (array) $estabelecimento;
                $empresa = $this->pegarEmpresa($linha['cnpj_basico']);

                if (empty($empresa)) {
                    echo "❌ Empresa não encontrada: {$linha['cnpj_basico']}" . PHP_EOL;
                    continue;
                }

                $linha['empresa_id'] = $empresa['id'] ?? null;
                if (empty($linha['empresa_id'])) {
                    continue;
                }

                $simples = $this->pegarSimples($linha['cnpj_basico']);
                $total = count($estabelecimentos);
                echo "Processando {$linha['cnpj_basico']} - {$empresa['razao_social']} ({$idx} de {$total}) com chunk de ".self::CHUNK_SIZE . PHP_EOL;
                flush();
                $dadosLote[] = $this->montarRegistro($estabelecimento->id, $empresa, $simples, $linha);
                $lastId      = $estabelecimento->id;

                if (count($dadosLote) >= self::CHUNK_SIZE) {
                    echo "Salvando lote com " . count($dadosLote) . " registros..." . PHP_EOL;
                    $this->salvarLote($dadosLote);
                    $dadosLote = [];
                }
            }
            DB::table('csv_progress')->updateOrInsert(
                ['filename' => 'EmpresaBase'],
                ['last_chunk' => $lastId, 'updated_at' => now()]
            );
            if (! empty($dadosLote)) {
                $this->salvarLote($dadosLote);
            }
        }
    }

    private function salvarLote(array $lote)
    {
        try {
            DB::table('base')->upsert($lote, ['cnpj'], array_keys($lote[0]));
            echo "✅ Inserido lote com " . count($lote) . " registros." . PHP_EOL;
            flush();
        } catch (Exception $e) {
            file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true) . PHP_EOL . print_r($lote, true), FILE_APPEND);
            echo '❌ Erro ao inserir lote: ' . $e->getMessage() . PHP_EOL;
        }
    }
}

// namespace App\UseCases;

// use DB;
// use Exception;

// class EmpresaBaseUseCase
// {

//     private function formatarData($data)
//     {
//         if ($data == null || $data == '') {
//             return null;
//         }
//         return (int) substr($data, 0, 4);
//     }
//     private function pegarEmpresa($baseCnpj)
//     {
//         return (array) DB::table('empresa')
//             ->where('cnpj_basico', $baseCnpj)
//             ->first();
//     }

//     private function pegarSimples($baseCnpj)
//     {
//         return collect(DB::table('simples')
//                 ->where('cnpj_basico', $baseCnpj)
//                 ->first())->toArray();
//     }

//     private function popularTabelaBase($idEstabelecimento, $empresa, $simples, $linha)
//     {
//         $capital_social            = (float) $empresa['capital_social'];
//         $natureza_juridica         = (int) $empresa['natureza_juridica'];
//         $porte                     = (int) $empresa['porte'];
//         $municipio                 = (int) $linha['municipio'];
//         $matriz_filial             = (int) $linha['matriz_filial'];
//         $situacao_cadastral        = (int) $linha['situacao_cadastral'];
//         $motivo_situacao_cadastral = (int) $linha['motivo_situacao_cadastral'];
//         $dados                     = [
//             'estabelecimento_id'        => $idEstabelecimento,
//                                                                      //empresa
//             'razao_social'              => $empresa['razao_social'], //ok
//             'natureza_juridica'         => $natureza_juridica,
//             'capital_social'            => $capital_social,
//             'porte'                     => $porte,
//                                                                             //estabelecimento
//             'empresa_id'                => $linha['empresa_id'],            //ok
//             'cnpj'                      => $linha['cnpj'],                  //ok
//             'nome_fantasia'             => $linha['nome_fantasia'],         //ok
//             'cnae_fiscal_principal'     => $linha['cnae_fiscal_principal'], //ok
//             'uf'                        => $linha['uf'],                    //ok
//             'municipio'                 => $municipio,
//             'bairro'                    => $linha['bairro'],                                     //ok
//             'data_inicio_atividade'     => $this->formatarData($linha['data_inicio_atividade']), //ok
//             'matriz'                    => $matriz_filial,
//             'simples'                   => ($simples['opcao_pelo_simples'] == 'S') ? 1 : 0, //ok
//             'mei'                       => ($simples['opcao_pelo_mei'] == 'S') ? 1 : 0,     //ok
//             'situacao_cadastral'        => $situacao_cadastral,
//             'data_situacao_cadastral'   => $this->formatarData($linha['data_situacao_cadastral']), //ok
//             'motivo_situacao_cadastral' => $motivo_situacao_cadastral,
//         ];
//         $keys = array_keys($dados);

//         try {
//             DB::table('base')->upsert([$dados], ['cnpj'], $keys);
//         } catch (Exception $e) {
//             file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true) . PHP_EOL . print_r($dados, true));

//             dd($e);exit;

//         }

//     }

//     public function __invoke()
//     {
//         // Processa cada $estabelecimento
//         try {

//             $limit   = 200000;
//             $lastId  = 8310000;
//             $temMais = true;

//             while ($temMais) {
//                 echo "Lendo estabelecimentos a partir do ID: " . $lastId . "..." . PHP_EOL;
//                 $inicio           = microtime(true);
//                 $estabelecimentos = DB::table('estabelecimento')
//                     ->where('id', '>', $lastId)
//                     ->orderBy('id')
//                     ->limit($limit)
//                     ->get();

//                 $temMais = !$estabelecimentos->isEmpty();
//                 $total = $estabelecimentos->count();
//                 foreach ($estabelecimentos as $idx =>  $estabelecimento) {
//                     $linha   = (array) $estabelecimento;
//                     $empresa = $this->pegarEmpresa($linha['cnpj_basico']);
//                     if (empty($empresa)) {
//                         echo PHP_EOL . "❌ Empresa não encontrada: " . $linha['cnpj_basico'] . PHP_EOL . json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL;
//                         continue;
//                     }
//                     $linha['empresa_id'] = ! empty($empresa) ? $empresa['id'] : null;
//                     $linha['cnpj']       = "{$linha['cnpj_basico']}{$linha['cnpj_ordem']}{$linha['cnpj_dv']}";
//                     $razaoSocial         = $empresa !== null ? $empresa['razao_social'] : null;
//                     if (empty($linha['nome_fantasia'])) {
//                         $linha['nome_fantasia'] = $razaoSocial;
//                     }
//                     $simples = $this->pegarSimples($linha['cnpj_basico']);
//                     if (empty($simples)) {
//                         $simples = [
//                             'opcao_pelo_simples'      => 'N',
//                             'data_opcao_pelo_simples' => null,
//                             'data_exclusao_simples'   => null,
//                             'opcao_pelo_mei'          => 'N',
//                             'data_opcao_mei'          => null,
//                             'data_exclusao_mei'       => null,
//                         ];
//                     }
//                     $linha['empresa_id'] = ! empty($empresa) ? $empresa['id'] : null;
//                     if (empty($linha['empresa_id'])) {
//                         continue;
//                     }
//                     $linha['cnpj'] = "{$linha['cnpj_basico']}{$linha['cnpj_ordem']}{$linha['cnpj_dv']}";

//                     $razaoSocial = $empresa !== null ? $empresa['razao_social'] : null;
//                     if (empty($linha['nome_fantasia'])) {
//                         $linha['nome_fantasia'] = $razaoSocial;
//                     }
//                     $idEstabelecimento = $estabelecimento->id ?? null;
//                     if ($idEstabelecimento) {
//                         $this->popularTabelaBase($idEstabelecimento, $empresa, $simples, $linha);
//                         echo "✅ Inserido {$linha['cnpj_basico']} dado {$idx} de {$total}" . PHP_EOL;
//                         flush();
//                     } else {
//                         echo '❌ Erro ao inserir estabelecimento: ' . $linha['cnpj_basico'] . PHP_EOL;
//                         exit;
//                     }

//                     $lastId = $estabelecimento->id;
//                 }

//             }

//         } catch (Exception $e) {
//             echo '❌ Erro ao processar: ' . $e->getMessage() . PHP_EOL;
//             exit;

//         }
//     }
// }
