<?php
namespace App\UseCases;

use DB;
use Exception;

abstract class CsvChunkReader
{
    protected int $chunkSize = 5000;

    private function trataTextoCSV($txt)
    {
        // Substitui \" por "
        $txt = str_replace('\\', '', $txt);

        // Aqui você pode aplicar outras limpezas adicionais
        // ex: normalização de acentos, trims, etc.

        return $txt;
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

    private function sanitizeCsv(string $file): string
    {
        $sanitizedFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cleaned_' . basename($file);

        $in  = fopen($file, 'r');
        $out = fopen($sanitizedFile, 'w');

        if (! $in || ! $out) {
            throw new \RuntimeException("Erro ao abrir arquivos para sanitização");
        }

        while (($line = fgets($in)) !== false) {
            $line = $this->trataTextoCSV($line); // limpa a linha inteira como texto
            fwrite($out, $line);
        }

        fclose($in);
        fclose($out);
        @unlink($file); // opcional, remove o arquivo original
        exec('rm -rf /tmp/unzip_*');
        return $sanitizedFile;
    }

    /**
     * Lê um arquivo CSV em chunks, processando via yield.
     *
     * @param string $file Caminho do arquivo CSV
     * @return \Generator
     */
    public function readCsv(string $file, $colunas, int $startChunk = 0): \Generator
    {
        $filename      = basename($file);
        // $sanitizedFile = $this->sanitizeCsv($file);

                exec('rm -rf /tmp/unzip_*');

        $handle        = fopen($file, 'r');
        if ($handle === false) {
            throw new Exception('Erro ao abrir o arquivo');
        }

        if ($startChunk != 0) {
            $startChunk -= 2;
            if ($startChunk < 0) {
                $startChunk = 0;
            }
            echo PHP_EOL . "Reiniciando leitura do arquivo: $filename a partir do chunk $startChunk" . PHP_EOL;
        }

        // Pular linhas já processadas
        $linesToSkip = $startChunk * $this->chunkSize;
        for ($i = 0; $i < $linesToSkip && ! feof($handle); $i++) {
            fgets($handle);
        }

        $currentChunk = $startChunk;
        $totalLines   = 0;
        while (! feof($handle)) {
            $chunk = [];
            for ($i = 0; $i < $this->chunkSize && ! feof($handle); $i++) {
                $row = fgetcsv($handle, separator: ';');
                if ($row === false || count($row) === 0) {
                    continue;
                }
                $totalLines++; // Conta a linha válida
                try {

                    $chunk[] = $this->processRow($row, $colunas);
                } catch (\InvalidArgumentException $e) {
                    // Loga o erro e continua
                    file_put_contents('/tmp/erro.txt', $e->getMessage() . PHP_EOL . print_r($row, true), FILE_APPEND);
                    exit;
                }
            }

            if (! empty($chunk)) {
                yield $chunk;

                // Atualiza o progresso no banco
                DB::table('csv_progress')->updateOrInsert(
                    ['filename' => $filename],
                    ['last_chunk' => ++$currentChunk, 'updated_at' => now()]
                );
            }
        }
        file_put_contents('/tmp/total_linhas.txt', "$file - $totalLines" . PHP_EOL, FILE_APPEND);
        fclose($handle);
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

        return array_combine($colunas, $row);
    }

}
