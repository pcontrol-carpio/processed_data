<?php
namespace App\UseCases;

use DB;
use Exception;
use Illuminate\Support\Facades\Log;

abstract class CsvChunkReader
{
    protected int $chunkSize;

    public function __construct()
    {
        $this->chunkSize = (int) config('app.csv_chunk_size');
    }

    private function trataTexto($txt)
    {
        // Limita a 255 caracteres
        $linha = substr($txt, 0, 255);

        // Remove acentos e converte para ASCII
        try {
            $linha = mb_convert_encoding($linha, 'UTF-8', 'UTF-8');
            $msg   = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $linha);
        } catch (Exception $e) {

            $msg = $linha;
        }



        $msg = str_replace([
            '!', '#', '&', '$', '%', '*', '+', '<', '>', ';', ',', '\\', '°', '"', '\'', '?', '[', ']', '{', '}', '=', '^', '`', '|',
        ], '', $msg);

        // Remove espaços duplicados e trim final
        $msg = preg_replace('/\s+/', ' ', $msg);
        $msg = trim($msg);

        return $msg;
    }

    /**
     * Lê um arquivo CSV em chunks, processando via yield.
     *
     * Cada chunk contém exatamente $chunkSize registros válidos (exceto o último),
     * então o progresso salvo em csv_progress (last_chunk) é convertido em
     * "registros a pular" de forma consistente no resume.
     *
     * @param string $file Caminho do arquivo CSV
     * @return \Generator
     */
    public function readCsv(string $file, $colunas, int $startChunk = 0): \Generator
    {
        $filename = basename($file);
        $fileSize = filesize($file);
        $inicio   = microtime(true);
        $reverse  = (bool) config('app.csv_read_reverse', false);

        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new Exception("Erro ao abrir o arquivo: $file");
        }

        $this->logCsv('info', "Iniciando leitura", [
            'arquivo'     => $filename,
            'tamanho'     => $fileSize,
            'chunk_size'  => $this->chunkSize,
            'start_chunk' => $startChunk,
            'reverse'     => $reverse,
        ]);

        if ($reverse && $startChunk !== 0) {
            $this->logCsv('warning', "Resume ignorado no modo reverso", [
                'arquivo'     => $filename,
                'start_chunk' => $startChunk,
            ]);
            $startChunk = 0;
        }

        if ($startChunk != 0) {
            // Volta 2 chunks por segurança (upsert é idempotente)
            $startChunk = max(0, $startChunk - 2);
        }

        $stats = [
            'linha_fisica'      => 0, // última linha física consumida
            'registros'         => 0, // registros válidos lidos (inclui os pulados no resume)
            'pulados_resume'    => 0,
            'linhas_vazias'     => 0,
            'multilinha'        => 0,
            'invalidos'         => 0,
            'chunks'            => 0,
        ];

        $reversePosition = $fileSize;

        // Pular registros já processados. Usa o mesmo parser (fgetcsv) da leitura:
        // pular com fgets conta linhas físicas e desalinha quando há campos multilinha.
        $recordsToSkip = $startChunk * $this->chunkSize;
        while ($stats['pulados_resume'] < $recordsToSkip) {
            $row = $this->nextRow($handle, $stats);
            if ($row === null) {
                break;
            }
            if ($row !== []) {
                $stats['pulados_resume']++;
            }
        }

        if ($recordsToSkip > 0) {
            $this->logCsv(feof($handle) ? 'warning' : 'info', "Resume: registros pulados", [
                'arquivo'          => $filename,
                'chunk_retomado'   => $startChunk,
                'esperado_pular'   => $recordsToSkip,
                'pulados'          => $stats['pulados_resume'],
                'linha_fisica'     => $stats['linha_fisica'],
                'offset'           => ftell($handle),
                'chegou_fim'       => feof($handle),
            ]);
            if (feof($handle)) {
                $this->logCsv('warning', "O progresso salvo em csv_progress já cobre o arquivo inteiro. "
                    . "Nada novo será lido. Se o arquivo é novo/reprocessamento, apague o registro em csv_progress.", [
                    'arquivo' => $filename,
                ]);
            }
        }

        $currentChunk = $startChunk;

        while (true) {
            $chunk = [];
            $eof   = false;

            while (count($chunk) < $this->chunkSize) {
                $linhaInicio = $stats['linha_fisica'] + 1;
                $row = $reverse
                    ? $this->previousRow($handle, $stats, $reversePosition)
                    : $this->nextRow($handle, $stats);

                if ($row === null) {
                    $eof = true;
                    break;
                }
                if ($row === []) {
                    continue; // linha vazia
                }

                try {
                    $chunk[] = $this->processRow($row, $colunas);


                    $stats['registros']++;
                } catch (\InvalidArgumentException $e) {
                    $stats['invalidos']++;
                    $this->logCsv('error', "Registro inválido ignorado", [
                        'arquivo'       => $filename,
                        'linhas_fisicas' => "$linhaInicio-{$stats['linha_fisica']}",
                        'offset'        => ftell($handle),
                        'erro'          => $e->getMessage(),
                    ]);
                }
            }

            if (! empty($chunk)) {
                $currentChunk++;
                $stats['chunks']++;

                $this->logCsv('debug', "Chunk lido", [
                    'arquivo'      => $filename,
                    'chunk'        => $currentChunk,
                    'registros'    => count($chunk),
                    'linha_fisica' => $stats['linha_fisica'],
                    'progresso'    => $fileSize
                        ? round(($reverse ? $fileSize - $reversePosition : ftell($handle)) / $fileSize * 100, 2) . '%'
                        : null,
                ]);

                yield $chunk;

                // Atualiza o progresso no banco (só depois que o consumidor processou o chunk)
                if (! $reverse) {
                    DB::table('csv_progress')->updateOrInsert(
                        ['filename' => $filename],
                        ['last_chunk' => $currentChunk, 'updated_at' => now()]
                    );
                }
            }

            if ($eof) {
                break;
            }
        }

        $offsetFinal = $reverse ? $reversePosition : ftell($handle);
        fclose($handle);

        $resumo = [
            'arquivo'         => $filename,
            'registros_lidos' => $stats['registros'],
            'pulados_resume'  => $stats['pulados_resume'],
            'invalidos'       => $stats['invalidos'],
            'multilinha'      => $stats['multilinha'],
            'linhas_vazias'   => $stats['linhas_vazias'],
            'linhas_fisicas'  => $stats['linha_fisica'],
            'chunks'          => $stats['chunks'],
            'ultimo_chunk'    => $currentChunk,
            'bytes_lidos'     => $offsetFinal,
            'tamanho'         => $fileSize,
            'tempo_s'         => round(microtime(true) - $inicio, 2),
            'reverse'         => $reverse,
        ];

        $leituraCompleta = $reverse ? $offsetFinal === 0 : $offsetFinal === $fileSize;
        if (! $leituraCompleta) {
            $this->logCsv('error', "Leitura terminou ANTES do fim do arquivo", $resumo);
            throw new \RuntimeException("Leitura de $filename parou no byte $offsetFinal de $fileSize");
        }

        $this->logCsv(($stats['invalidos'] || $stats['multilinha']) ? 'warning' : 'info', "Leitura finalizada", $resumo);
    }

    /**
     * Lê a linha física anterior. O modo reverso é destinado a arquivos sem
     * campos multilinha e não altera o progresso persistido da leitura normal.
     */
    private function previousRow($handle, array &$stats, int &$position): ?array
    {
        if ($position <= 0) {
            return null;
        }

        $line = '';
        while ($position > 0) {
            $position--;
            fseek($handle, $position);
            $character = fread($handle, 1);

            if ($character === "\n") {
                if ($line === '') {
                    continue;
                }
                break;
            }

            $line = $character . $line;
        }

        $line = rtrim($line, "\r");
        $stats['linha_fisica']++;

        if ($line === '') {
            $stats['linhas_vazias']++;
            return [];
        }

        if (substr_count($line, '"') % 2 !== 0) {
            throw new \RuntimeException(
                'Modo reverso não suporta registros CSV com campos multilinha ou aspas desbalanceadas'
            );
        }

        return str_getcsv($line, ';', '"', '');
    }

    /**
     * Lê o próximo registro do CSV.
     * Retorna null no fim do arquivo, [] para linha vazia, ou o array de campos.
     */
    private function nextRow($handle, array &$stats): ?array
    {
        // escape: '' desativa o "\" como caractere de escape, que fazia o fgetcsv
        // tratar \" como aspas escapadas e engolir linhas seguintes.
        $row = fgetcsv($handle, null, ';', '"', '');

        if ($row === false) {
            if (! feof($handle)) {
                throw new \RuntimeException('Erro de leitura no arquivo (fgetcsv retornou false antes do EOF) no offset ' . ftell($handle));
            }
            return null;
        }

        if ($row === [null]) {
            $stats['linha_fisica']++;
            $stats['linhas_vazias']++;
            return [];
        }

        // Um registro pode ocupar mais de uma linha física se tiver quebra de linha
        // dentro de aspas. Muitas linhas num registro só = aspas desbalanceadas.
        $quebras = 0;
        foreach ($row as $campo) {
            $quebras += substr_count((string) $campo, "\n");
        }
        $stats['linha_fisica'] += 1 + $quebras;

        if ($quebras > 0) {
            $stats['multilinha']++;
            $this->logCsv('warning', "Registro multilinha (possíveis aspas desbalanceadas)", [
                'linha_fisica_fim' => $stats['linha_fisica'],
                'linhas_ocupadas'  => 1 + $quebras,
                'offset'           => ftell($handle),
                'inicio'           => substr(implode(';', $row), 0, 300),
            ]);
        }

        return $row;
    }

    private function logCsv(string $level, string $message, array $context = []): void
    {
        Log::channel('csv')->{$level}($message, $context);

        if ($level !== 'debug') {
            echo PHP_EOL . '[' . strtoupper($level) . "] $message " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
    }

    /**
     * Permite sobrescrever para processar cada linha conforme necessidade
     * Por padrão, retorna a linha crua.
     */
    protected function processRow(array $row, $colunas)
    {
        foreach ($row as &$line) {
            $line = $this->trataTexto($line);
        }

        if (count($colunas) !== count($row)) {
            throw new \InvalidArgumentException(
                'Número de colunas e valores não coincide. ' .
                'Esperado: ' . count($colunas) . ', recebido: ' . count($row) .
                '. Dados recebidos: [' . implode(', ', $row) . ']' .
                ' Colunas esperadas: [' . implode(', ', $colunas) . ']'
            );

        }

        $row = array_combine($colunas, $row);
        //loga todas as linhas processadas
        $this->logCsv('info', "Processando linha", ['cnpj_basico' => $row['cnpj_basico'] ?? null , 'razao_social' => $row['razao_social'] ?? null]);
        //me alerta aqui se achar o cnpj_basico 652936618 ou razao social contiver Aurius
        if (isset($row['cnpj_basico']) && $row['cnpj_basico'] === '65293661') {
            $this->logCsv('warning', "Encontrado cnpj_basico 65293661", ['row' => $row]);
        }

        if (isset($row['razao_social']) && str_contains($row['razao_social'], 'AURIUS')) {
            $this->logCsv('warning', "Encontrado razao social contendo 'AURIUS'", ['row' => $row]);
        }
        return $row;
    }

}
