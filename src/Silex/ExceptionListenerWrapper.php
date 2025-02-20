<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Wraps exception listeners.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionListenerWrapper
{
    protected $app;
    protected $callback;

    /**
     * Constructor.
     *
     * @param Application $app      An Application instance
     * @param callable    $callback
     */
    public function __construct(Application $app, $callback)
    {
        $this->app = $app;
        $this->callback = $callback;
    }

    public function __invoke(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $this->callback = $this->app['callback_resolver']->resolveCallback($this->callback);

        if (!$this->shouldRun($exception)) {
            return;
        }

        $code = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        $response = call_user_func($this->callback, $exception, $event->getRequest(), $code, $event);

        $this->ensureResponse($response, $event);
    }

    protected function shouldRun(\Throwable $exception)
    {
        if (is_array($this->callback)) {
            $callbackReflection = new \ReflectionMethod($this->callback[0], $this->callback[1]);
        } elseif (is_object($this->callback) && !$this->callback instanceof \Closure) {
            $callbackReflection = new \ReflectionObject($this->callback);
            $callbackReflection = $callbackReflection->getMethod('__invoke');
        } else {
            $callbackReflection = new \ReflectionFunction($this->callback);
        }

        if ($callbackReflection->getNumberOfParameters() > 0) {
            $parameters = $callbackReflection->getParameters();
            $expectedException = $parameters[0];
            if (($type = $expectedException->getType()) && !is_subclass_of($exception, $type->getName())) {
              return false;
            }
        }

        return true;
    }

    protected function ensureResponse($response, ExceptionEvent $event)
    {
        if ($response instanceof Response) {
            $event->setResponse($response);
        } else {
            $viewEvent = new ViewEvent($this->app['kernel'], $event->getRequest(), $event->getRequestType(), $response);
            $this->app['dispatcher']->dispatch($viewEvent, KernelEvents::VIEW);

            if ($viewEvent->hasResponse()) {
                $event->setResponse($viewEvent->getResponse());
            }
        }
    }
}
