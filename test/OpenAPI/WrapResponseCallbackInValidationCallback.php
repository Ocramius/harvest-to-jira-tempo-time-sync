<?php

declare(strict_types=1);

namespace TimeSyncTest\OpenAPI;

use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psl\Json;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Yaml;

use function Psl\File\read;
use function str_replace;

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

            $operation = $requestValidator->validate(self::fixUpKnownQueryArrayParameters($request));

            $response = $callback($request);

            $responseValidator->validate($operation, $response);

            return $response;
        };
    }

    /**
     * Converts known array query parameters to PHP-array-alike syntax
     *
     * @see https://github.com/thephpleague/openapi-psr7-validator/issues/181
     * @see https://github.com/thephpleague/openapi-psr7-validator/pull/182
     * @see https://swagger.io/docs/specification/v3_0/serialization/
     */
    private static function fixUpKnownQueryArrayParameters(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();

        return $request->withUri(
            $uri->withQuery(str_replace('issueId=', 'issueId[]=', $uri->getQuery())),
        );
    }
}
