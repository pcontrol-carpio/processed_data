<?php
namespace App\UseCases;

use DB;
use Exception;


abstract class CsvChunkReader
{
    protected int $chunkSize = 3000;


    private function trataTexto($txt)
{
    // Limita a 255 caracteres
    $linha = substr($txt, 0, 255);

    // Remove acentos e converte para ASCII
  try{
    $linha = mb_convert_encoding($linha, 'UTF-8', 'UTF-8');
      $msg = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $linha);
  }catch(Exception $e){

      $msg = $linha;
  }

    // Remove caracteres especiais indesejados (adicione conforme sua necessidade)
    $msg = str_replace([
        '!', '@', '#', '&', '$', '%', '*', '+', '-', '<', '>', '/', ';', ',', '\\', '.', '_', '(', ')', '°', '"', '\'', '?', '[', ']', '{', '}', '=', '^', '`', '|'
    ], '', $msg);

    // Remove espaços duplicados e trim final
    $msg = preg_replace('/\s+/', ' ', $msg);
    $msg = trim($msg);

    return $msg;
}

    /**
     * Lê um arquivo CSV em chunks, processando via yield.
     *
     * @param string $file Caminho do arquivo CSV
     * @return \Generator
     */
   public function readCsv(string $file, $colunas, int $startChunk = 0): \Generator
{
    $filename = basename($file);

    $handle = fopen($file, 'r');
    if ($handle === false) {
        throw new Exception('Erro ao abrir o arquivo');
    }

    if($startChunk != 0){
            $startChunk -= 2 ;
        if($startChunk < 0){
            $startChunk = 0;
        }
        echo PHP_EOL."Reiniciando leitura do arquivo: $filename a partir do chunk $startChunk" . PHP_EOL;
    }

    // Lê e ignora o cabeçalho
    $header = fgetcsv($handle, separator: ';');

    // Pular linhas já processadas
    $linesToSkip = $startChunk * $this->chunkSize;
    for ($i = 0; $i < $linesToSkip && !feof($handle); $i++) {
        fgets($handle);
    }

    $currentChunk = $startChunk;

    while (!feof($handle)) {
        $chunk = [];
        for ($i = 0; $i < $this->chunkSize && !feof($handle); $i++) {
            $row = fgetcsv($handle, separator: ';');
            if ($row === false || count($row) === 0) {
                continue;
            }

            $chunk[] = $this->processRow($row, $colunas);
        }

        if (!empty($chunk)) {
            yield $chunk;

            // Atualiza o progresso no banco
            DB::table('csv_progress')->updateOrInsert(
                ['filename' => $filename],
                ['last_chunk' => ++$currentChunk, 'updated_at' => now()]
            );
        }
    }

    fclose($handle);
    unlink($file); // opcional
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
            '. Dados recebidos: [' . implode(', ', $row) . ']'
        );

    }

    return array_combine($colunas, $row);
}

}
