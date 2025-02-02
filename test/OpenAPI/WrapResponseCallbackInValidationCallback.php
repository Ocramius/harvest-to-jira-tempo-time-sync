<?php

declare(strict_types=1);

namespace TimeSyncTest\OpenAPI;

use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psl\Json;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Yaml;

use function Psl\File\read;

final class WrapResponseCallbackInValidationCallback
{
    /**
     * @param non-empty-string                              $openApiSpecFile
     * @param callable(RequestInterface): ResponseInterface $callback
     *
     * @return callable(RequestInterface): ResponseInterface
     */
    public static function wrap(string $openApiSpecFile, callable $callback): callable
    {
        return static function (RequestInterface $request) use ($openApiSpecFile, $callback): ResponseInterface {
            $validatorBuilder = (new ValidatorBuilder())
                ->fromJson(Json\encode(Yaml::parse(read($openApiSpecFile))));

            $requestValidator  = $validatorBuilder->getRequestValidator();
            $responseValidator = $validatorBuilder->getResponseValidator();

            $operation = $requestValidator->validate($request);

            $response = $callback($request);

            $responseValidator->validate($operation, $response);

            return $response;
        };
    }
}
