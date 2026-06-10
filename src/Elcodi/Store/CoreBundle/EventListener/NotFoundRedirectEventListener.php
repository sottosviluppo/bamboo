<?php

namespace Elcodi\Store\CoreBundle\EventListener;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use Mmoreram\ControllerExtraBundle\Exceptions\EntityNotFoundException as ControllerEntityNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

class NotFoundRedirectEventListener
{
    /** @var RouterInterface */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        // file_put_contents(
        //     '/var/www/clients/client1/web26/private/404_debug.log',
        //     date('Y-m-d H:i:s').' ['.get_class($exception).'] '.$exception->getMessage()."\n",
        //     FILE_APPEND
        // );

        if (
            !($exception instanceof NotFoundHttpException)
            && !($exception instanceof DoctrineEntityNotFoundException)
            && !($exception instanceof ControllerEntityNotFoundException)
        ) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('store_homepage')));
    }
}
