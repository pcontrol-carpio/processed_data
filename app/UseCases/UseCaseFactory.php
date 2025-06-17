<?
namespace App\UseCases;
class UseCaseFactory
{
    protected array $map = [
        'Empresas'        => EmpresaUseCase::class,
        'Simples'        => SimplesUseCase::class,
         'Socios'          => SocioUseCase::class,
         'Estabelecimentos'=> EstabelecimentoUseCase::class,
        // // Adicione outros tipos e suas classes aqui...
    ];

    public function __invoke($file, $type)
    {
        if (!isset($this->map[$type])) {
            throw new \InvalidArgumentException("Tipo de UseCase inválido: $type");
        }

        $useCaseClass = $this->map[$type];
        $useCase = new $useCaseClass();
        // Garante que é invokable
        if (!is_callable($useCase)) {
            throw new \LogicException("Classe $useCaseClass não é invocável");
        }
        // Executa o UseCase passando o arquivo
        return $useCase($file);
    }
}
