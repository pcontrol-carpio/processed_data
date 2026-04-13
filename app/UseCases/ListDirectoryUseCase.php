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

        // // Busca HTML remoto e extrai os links de diretórios válidos
        // $html = Http::get($url)->body();
        // preg_match_all('/href="([^"]+)"/i', $html, $matches);
        // $pastasRemotas = collect($matches[1])->filter(function ($href) {

        //     return str_ends_with($href, '.zip') && (str_starts_with($href, 'Empresas') || str_starts_with($href, 'Estabelecimentos') || str_starts_with($href, 'Simples') || str_starts_with($href, 'Socios'))
        //     ;
        // });
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
