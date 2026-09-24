<?php
namespace App\UseCases;

use DB;
use Exception;
use stdClass;

class EmpresaBaseUseCase
{

    private function isIndustria(string $cnae): bool
    {
        $prefixo = str_pad(substr($cnae, 0, 2), 2, '0', STR_PAD_LEFT);

        $industriais = [
            '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17',
            '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
            '31', '32', '33',
        ];

        return in_array($prefixo, $industriais, true);
    }

 /**
     * @param array $company
     * @return int
     */
    private function checkFuncionarios(array $company): int
    {
        $industria = $this->isIndustria((string)$company['cnae_fiscal_principal']);
        $porte = (int)$company['porte'];

        if (empty($porte)) {
            return 0;
        }

        if ($industria) {
            switch ($porte) {
                case 1:
                    echo 'Indústria | MICRO EMPRESA: 2 a 19 funcionários' . PHP_EOL;
                    return 2;
                case 3:
                    echo 'Indústria | PEQUENO PORTE: 20 a 99 funcionários' . PHP_EOL;
                    return 3;
                case 5:
                    echo 'Indústria | DEMAIS: 100 a 99.999 funcionários' . PHP_EOL;
                    return 4;
            }
        } else {
            switch ($porte) {
                case 1:
                    echo 'Não Indústria | MICRO EMPRESA: 2 a 9 funcionários' . PHP_EOL;
                    return 5;
                case 3:
                    echo 'Não Indústria | PEQUENO PORTE: 10 a 49 funcionários' . PHP_EOL;
                    return 6;
                case 5:
                    echo 'Não Indústria | DEMAIS: 50 a 99 funcionários' . PHP_EOL;
                    return 7;
            }
        }

        return 0;
    }

    /**
     * @param int|null $simples
     * @param int|null $porte
     * @return int
     */
    private function checkFaturamento(?int $simples, ?int $porte): int
    {
        if (empty($porte)) {
            return 0;
        }

        if ($simples === 1) {
            switch ($porte) {
                case 1:
                    echo 'Simples | ME: R$ 81.000,01 até R$ 360.000,00' . PHP_EOL;
                    return 2;
                case 3:
                    echo 'Simples | EPP: R$ 360.000,01 até R$ 4.800.000,00' . PHP_EOL;
                    return 3;
                case 5:
                    echo 'Simples | Acima do Simples: R$ 4.800.000,01 até infinito' . PHP_EOL;
                    return 4;
                default:
                    return 0;
            }
        }

        switch ($porte) {
            case 1:
                echo 'Lucro Real/Presumido | ME: Até R$ 360.000,00' . PHP_EOL;
                return 5;
            case 3:
                echo 'Lucro Real/Presumido | EPP: R$ 360.000,01 até R$ 4.800.000,00' . PHP_EOL;
                return 6;
            case 5:
                echo 'Lucro Real/Presumido | Demais: R$ 4.800.000,01 até R$ 78.000.000,00 ou mais' . PHP_EOL;
                return 7;
            default:
                return 0;
        }
    }

    private const CHUNK_SIZE = 3000;

    private function formatarData($data)
    {
        return empty($data) ? null : (int) substr($data, 0, 4);
    }

    private function pegarEmpresa($baseCnpj)
    {
        return (array) DB::table('empresa')
            ->where('cnpj_basico', operator: $baseCnpj)
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
        $mei = $simples['opcao_pelo_mei'] === 'S' ? 1 : 0;
        $opcaoSimples = $simples['opcao_pelo_simples'] === 'S' ? 1 : 0;

        $empresa['cnae_fiscal_principal'] = $linha['cnae_fiscal_principal'];
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
            'simples'                   => $opcaoSimples,
            'mei'                       => $mei,
            'situacao_cadastral'        => (int) $linha['situacao_cadastral'],
            'data_situacao_cadastral'   => $this->formatarData($linha['data_situacao_cadastral']),
            'motivo_situacao_cadastral' => (int) $linha['motivo_situacao_cadastral'],
            'funcionarios'              => ($mei == 1) ? 1 : $this->checkFuncionarios($empresa),
            'faturamento'               => ($mei == 1) ? 1: $this->checkFaturamento($opcaoSimples, (float) $empresa['porte']),

        ];
    }

    public function __invoke()
    {
        $limit = 30000;

        $lastId = DB::table('csv_progress')
            ->where('filename', 'EmpresaBase')
            ->value('last_chunk') ?? null;

        // Pega o maior ID se não houver progresso salvo
        if ($lastId === null) {
            $lastId = DB::table('estabelecimento')->max('id');
        }

        while ($lastId > 0) {
            echo "Lendo até $limit estabelecimentos a partir do ID: $lastId..." . PHP_EOL;

            $estabelecimentos = DB::table('estabelecimento')
                ->where('id', '<=', $lastId)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get();

            if ($estabelecimentos->isEmpty()) {
                break;
            }

            $nextId = (int) $estabelecimentos->last()->id - 1;
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
                $total   = count($estabelecimentos);

                echo "Processando {$linha['cnpj_basico']} - {$empresa['razao_social']} ({$idx} de {$total})" . PHP_EOL;
                flush();

                $dadosLote[] = $this->montarRegistro($estabelecimento->id, $empresa, $simples, $linha);
            }

            // Salva tudo de uma vez (upsert com SQL puro)
            if (! empty($dadosLote)) {
                echo "💾 Inserindo " . count($dadosLote) . " registros com insert direto..." . PHP_EOL;
                $this->salvarLoteDireto($dadosLote);
            }
            // Atualiza progresso
            DB::table('csv_progress')->updateOrInsert(
                ['filename' => 'EmpresaBase'],
                ['last_chunk' => $nextId, 'updated_at' => now()]
            );
            $lastId = $nextId;
        }
    }

    private function salvarLoteDireto(array $dados)
    {
        try {
            if (empty($dados)) {
                return;
            }

            $colunas    = array_keys($dados[0]);
            $colunasSql = implode(',', array_map(fn($c) => "`$c`", $colunas));
            $valuesSql  = [];

            foreach ($dados as $linha) {
                $valoresEscapados = array_map(fn($valor) => $valor === null ? 'NULL' : DB::getPdo()->quote($valor), $linha);

                $valuesSql[] = '(' . implode(',', $valoresEscapados) . ')';
            }

            $updateFields = array_diff($colunas, ['id']); // exemplo: não atualiza a PK
            $onDuplicate  = implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", $updateFields));

            $sql = "INSERT INTO `base` ($colunasSql) VALUES " . implode(',', $valuesSql)
                . " ON DUPLICATE KEY UPDATE $onDuplicate";

            DB::statement($sql);
        } catch (Exception $e) {
            file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true));
            echo '❌ Erro ao processar lote: ' . $e->getMessage() . PHP_EOL;
            throw $e;
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
            dd($e->getMessage());
            echo '❌ Erro ao inserir lote: ' . $e->getMessage() . PHP_EOL;
        }
    }
}
