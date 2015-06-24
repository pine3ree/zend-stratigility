<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Stratigility;

use Exception;
use Zend\Escaper\Escaper;

/**
 * Handle incomplete requests
 */
class FinalHandler
{
    /**
     * @var array
     */
    private $options;

    /**
     * @param array $options Options that change default override behavior.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Handle incomplete requests
     *
     * This handler should only ever be invoked if Next exhausts its stack.
     * When that happens, we determine if an $err is present, and, if so,
     * create a 500 status with error details.
     *
     * Otherwise, a 404 status is created.
     *
     * @param Http\Request $request Request instance.
     * @param Http\Response $response Response instance.
     * @param mixed $err
     * @return Http\Response
     */
    public function __invoke(Http\Request $request, Http\Response $response, $err = null)
    {
        if ($err) {
            return $this->handleError($err, $request, $response);
        }

        return $this->create404($request, $response);
    }

    /**
     * Handle an error condition
     *
     * Use the $error to create details for the response.
     *
     * @param mixed $error
     * @param Http\Request $request Request instance.
     * @param Http\Response $response Response instance.
     * @return Http\Response
     */
    private function handleError($error, Http\Request $request, Http\Response $response)
    {
        $response = $response->withStatus(
            Utils::getStatusCode($error, $response)
        );

        $message = $response->getReasonPhrase() ?: 'Unknown Error';
        if (! isset($this->options['env'])
            || $this->options['env'] !== 'production'
        ) {
            $message = $this->createDevelopmentErrorMessage($error);
        }

        $response = $response->end($message);

        $this->triggerError($error, $request, $response);

        return $response;
    }

    /**
     * Create a 404 status in the response
     *
     * @param Http\Request $request Request instance.
     * @param Http\Response $response Response instance.
     * @return Http\Response
     */
    private function create404(Http\Request $request, Http\Response $response)
    {
        $response        = $response->withStatus(404);
        $originalRequest = $request->getOriginalRequest();
        $uri             = $originalRequest->getUri();
        $escaper         = new Escaper();
        $message         = sprintf(
            "Cannot %s %s\n",
            $escaper->escapeHtml($request->getMethod()),
            $escaper->escapeHtml((string) $uri)
        );
        return $response->end($message);
    }

    private function createDevelopmentErrorMessage($error)
    {
        if ($error instanceof Exception) {
            $message  = $error->getMessage() . "\n";
            $message .= $error->getTraceAsString();
        } elseif (is_object($error) && ! method_exists($error, '__toString')) {
            $message = sprintf('Error of type "%s" occurred', get_class($error));
        } else {
            $message = (string) $error;
        }

        $escaper = new Escaper();
        return $escaper->escapeHtml($message);
    }

    /**
     * Trigger the error listener, if present
     *
     * @param mixed $error
     * @param Http\Request $request
     * @param Http\Response $response
     */
    private function triggerError($error, Http\Request $request, Http\Response $response)
    {
        if (! isset($this->options['onerror'])
            || ! is_callable($this->options['onerror'])
        ) {
            return;
        }

        $onError = $this->options['onerror'];
        $onError($error, $request, $response);
    }
}
