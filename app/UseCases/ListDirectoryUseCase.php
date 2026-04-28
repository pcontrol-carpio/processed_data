<?php
namespace App\UseCases;

use Illuminate\Support\Facades\Http;

class ListDirectoryUseCase
{

    public function __invoke($url)
    {
        // Busca todas as pastas já processadas
        // $pastasProcessadas = DB::table('processados')->where('completo',1)->pluck('pasta');

        $pastasRemotas = array();
         $pastasRemotas[] = 'Simples.zip';
        for($i = 0; $i <= 9 ; $i++){
            $pastasRemotas[] = "Empresas$i.zip";
            $pastasRemotas[] = "Estabelecimentos$i.zip";
            $pastasRemotas[] = "Socios$i.zip";

        }

        if(empty($pastasRemotas)){
            return null;
        }

        $pastasRemotas = collect($pastasRemotas);
        // Remove pastas já processadas
        $ordem = [
            'Empresas'         => $pastasRemotas->filter(fn($a) => str_starts_with($a, 'Empresas'))->sort()->values(),
            'Simples'          => $pastasRemotas->filter(fn($a) => str_starts_with($a, 'Simples'))->sort()->values(),
            'Socios'           => $pastasRemotas->filter(fn($a) => str_starts_with($a, 'Socios'))->sort()->values(),
            'Estabelecimentos' => $pastasRemotas->filter(fn($a) => str_starts_with($a, 'Estabelecimentos'))->sort()->values(),
        ];


        return $ordem;
        // Retorno pode ser um array, JSON, ou o que preferir
    }

}
