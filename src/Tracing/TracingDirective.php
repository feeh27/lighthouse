<?php

namespace Nuwave\Lighthouse\Tracing;

use Carbon\Carbon;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class TracingDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Nuwave\Lighthouse\Tracing\Tracing
     */
    protected $tracing;

    /**
     * TracingDirective constructor.
     *
     * @param  \Nuwave\Lighthouse\Tracing\Tracing  $tracing
     * @return void
     */
    public function __construct(Tracing $tracing)
    {
        $this->tracing = $tracing;
    }

    const NAME = 'tracing';

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        $value = $next($value);

        $resolver = $value->getResolver();

        return $value->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $start = Carbon::now();
            $result = $resolver($root, $args, $context, $resolveInfo);

            ($result instanceof Deferred)
                ? $result->then(function () use ($resolveInfo, $start): void {
                    $this->tracing->record(
                        $resolveInfo,
                        $start,
                        Carbon::now()
                    );
                })
                : $this->tracing->record(
                    $resolveInfo,
                    $start,
                    Carbon::now()
                );

            return $result;
        });
    }
}
