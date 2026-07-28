<?php

namespace App\Http\Middleware;

use App\Support\EditorialContent;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureEditorialVersion
{
    public function __construct(private readonly EditorialContent $content) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientVersion = $request->input('_editorial_version');

        if (! is_string($clientVersion) || $clientVersion === '') {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model || ! $this->content->supports($parameter)) {
                continue;
            }

            if ($this->content->version($parameter) !== $clientVersion) {
                throw ValidationException::withMessages([
                    'editorial_conflict' => 'Материал уже изменён в другой вкладке или другим сотрудником. Обновите страницу и сравните версии.',
                ]);
            }
        }

        return $next($request);
    }
}
