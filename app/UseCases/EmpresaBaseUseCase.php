<?php
namespace App\UseCases;

use DB;
use Exception;

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

  private function getFaturamentoRangeId(float $capitalSocial): ?int
{
    return DB::table('faturamento_range')
        ->where('min', '<=', $capitalSocial)
        ->where('max', '>=', $capitalSocial)
        ->value('id');
}


   private function getFuncionarioRangeId(string $cnae, int $porte): ?int
{
    $industria = $this->isIndustria($cnae);

    $faixa = match ($porte) {
        0 => [0, 1],       // MEI
        1 => [2, 9],       // ME
        3 => [10, 49],     // EPP
        5 => [50, 499],    // Média
        9 => [500, 999999] // Grande
    };

    return DB::table('funcionarios_range')
        ->where('industria', $industria)
        ->where('min_funcionarios', '<=', $faixa[0])
        ->where('max_funcionarios', '>=', $faixa[1])
        ->value('id');
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
            'funcionarios'              => $this->getFuncionarioRangeId(
                $linha['cnae_fiscal_principal'],
                (int) $empresa['porte']
            ),
            'faturamento'               => $this->getFaturamentoRangeId((float) $empresa['capital_social']),

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

        $temMais = true;

        while ($temMais && $lastId > 0) {
            $startId = max(1, $lastId - $limit + 1);
            echo "Lendo estabelecimentos do ID: $lastId descendo até $startId..." . PHP_EOL;

            $estabelecimentos = DB::table('estabelecimento')
                ->whereBetween('id', [$startId, $lastId])
                ->orderBy('id', 'desc')
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
                ['last_chunk' => $startId - 1, 'updated_at' => now()]
            );
            exit;
            $lastId = $startId - 1;
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
