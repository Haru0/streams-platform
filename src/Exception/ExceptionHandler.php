<?php namespace Anomaly\Streams\Platform\Exception;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Class ExceptionHandler
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class ExceptionHandler extends Handler
{

    /**
     * The exception instance.
     *
     * @var Exception
     */
    protected $original;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $internalDontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * Prepare the exception for handling.
     *
     * But first stash the original exception.
     *
     * @param Exception $e
     * @return Exception
     */
    protected function prepareException(Exception $e)
    {
        $this->original = $e;

        return parent::prepareException($e); // TODO: Change the autogenerated stub
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request $request
     * @param  Exception $e
     * @return Response|\Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Exception $e)
    {
        /**
         * Have to catch this for some reason.
         * Not sure why our handler passes this.
         */
        if ($e instanceof AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }

        /**
         * Redirect to a custom page if needed
         * in the event that their is one defined.
         */
        if ($e instanceof NotFoundHttpException && $redirect = config('streams::404.redirect')) {
            return redirect($redirect);
        }

        return parent::render($request, $e);
    }

    /**
     * Render the given HttpException.
     *
     * @param HttpExceptionInterface $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(HttpExceptionInterface $e)
    {
        /**
         * Always show exceptions
         * if not in debug mode.
         */
        if (env('APP_DEBUG') === true) {
            return $this->convertExceptionToResponse($e);
        }

        $summary = 'No Message';//$e->getMessage();
        $headers = $e->getHeaders();
        $code    = $e->getStatusCode();
        $name    = trans("streams::error.{$code}.name");
        $message = trans("streams::error.{$code}.message");
        $id      = $this->container->make(ExceptionIdentifier::class)->identify($this->original);

        if (view()->exists($view = "streams::errors/{$code}")) {
            return response()->view($view, compact('id', 'code', 'name', 'message', 'summary'), $code, $headers);
        }

        return response()->view(
            'streams::errors/error',
            compact('id', 'code', 'name', 'message', 'summary'),
            $code,
            $headers
        );
    }

    /**
     * Report the exception.
     *
     * But first make sure it's stashed.
     *
     * @param Exception $e
     * @return mixed
     */
    public function report(Exception $e)
    {
        $this->original = $e;

        return parent::report($e);
    }

    /**
     * Get the default context variables for logging.
     *
     * @return array
     */
    protected function context()
    {
        try {
            return array_filter(
                [
                    'user'       => \Auth::id(),
                    'email'      => \Auth::user() ? \Auth::user()->email : null,
                    'url'        => request() ? request()->fullUrl() : null,
                    'identifier' => $this->container->make(ExceptionIdentifier::class)->identify($this->original),
                ]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Auth\AuthenticationException $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if ($request->segment(1) === 'admin') {
            return redirect()->guest('admin/login');
        } else {
            return redirect()->guest('login');
        }
    }
}
