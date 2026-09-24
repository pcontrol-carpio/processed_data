<?php

namespace Tests\Unit;

use App\UseCases\CsvChunkReader;
use Tests\TestCase;

class CsvChunkReaderTest extends TestCase
{
    public function test_it_reads_csv_from_the_end_when_reverse_mode_is_enabled(): void
    {
        config([
            'app.csv_chunk_size' => 2,
            'app.csv_read_reverse' => true,
        ]);

        $file = tempnam(sys_get_temp_dir(), 'csv-reverse-');
        file_put_contents($file, "1;Primeira\n2;Segunda\n3;Terceira\n");

        $reader = new class extends CsvChunkReader {};

        try {
            $chunks = iterator_to_array($reader->readCsv($file, ['cnpj_basico', 'razao_social']));
        } finally {
            unlink($file);
        }

        $this->assertSame(['3', '2'], array_column($chunks[0], 'cnpj_basico'));
        $this->assertSame(['1'], array_column($chunks[1], 'cnpj_basico'));
    }
}
