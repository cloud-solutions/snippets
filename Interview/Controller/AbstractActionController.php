<?php

namespace Survey\Controller;

use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Survey\Constants\Environment;
use Survey\Constants\Route;
use Survey\Controller\Plugin\AjaxResponse;
use Survey\Db\Entity\AbstractUser;
use Survey\Db\Repository\Exception\ExceptionInterface as DbException;
use Survey\Di\DiWrapper;
use Survey\View\Helper\Messenger;
use Throwable;

/**
 * @method HttpResponse gotoUrl($url)
 * @method HttpResponse gotoSimple($action = null, $controller = null, array $urlParams = [], $reuseMatchedParams = false)
 * @method DiWrapper di()
 * ...
 */
abstract class AbstractActionController extends \Laminas\Mvc\Controller\AbstractActionController
{
    // Url params
    public const PARAM_JUST_LOGGED_IN = 'justLoggedIn';

    protected ?AbstractUser $user = null;

    protected ViewModel $viewModel;

    public function onDispatch(MvcEvent $mvcEvent): mixed
    {
        try {
            // Provide the logged in user
            $acl        = $this->di()->acl();
            $this->user = $acl->getUser();

            // Set up locale (must happen before init() to provide route locale default parameter)
            $locale = $this->di()->initLocale()->initLocale($mvcEvent->getRouteMatch());
            // Save user locale if locale in the URL has changed
            if ($this->user && $this->user->getLocale() !== $locale) {
                $this->user->setLocale($locale);
                $this->di()->db()->flush($this->user);
            }

            $roleName = $acl->getRole()->getName();
            $userID   = $this->user?->getId();
            $this->di()->sentryLogger()->init($mvcEvent->getViewModel(), $locale, $roleName, $userID);

            // Set up acl
            $acl->init($this->getName(), $this->getActionName());

            return parent::onDispatch($mvcEvent);
        } catch (Throwable $exception) {
            if ($this->request->isXmlHttpRequest()) {
                $this->di()->sentryLogger()->logException($exception);

                if (!Environment::isProduction()) {
                    // Render exception as modal message
                    $content = $this->di()->renderer()->render('error/_exception.phtml', ['exception' => $exception]);
                    return $this->ajaxResponse()->setup(HttpStatus::INTERNAL_SERVER_ERROR, AjaxResponse::RESPONSE_MODAL_MESSAGE, ['content' => $content]);
                }

                return $this->ajaxResponse()->setup(HttpStatus::INTERNAL_SERVER_ERROR);
            }

            throw $exception;
        }
    }

    protected function getUrlParam(string $name, string $default = null): ?string
    {
        $routeMatch = $this->getEvent()->getRouteMatch();
        return $routeMatch ? $routeMatch->getParam($name, $default) : $default;
    }

    protected function isPost(): bool
    {
        return $this->getRequest()->isPost();
    }
}
