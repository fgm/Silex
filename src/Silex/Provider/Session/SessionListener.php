<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Provider\Session;

use Pimple\Container;
use Symfony\Component\HttpKernel\EventListener\SessionListener as BaseSessionListener;

/**
 * Sets the session in the request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SessionListener extends BaseSessionListener
{
    private $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
        // 変更箇所：継承元クラスのcontainer変数にapp情報渡す（渡せていないの既存の段階でバグな気がする）
        $this->container = $this->app;
    }

    protected function getSession(): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        if (!isset($this->app['session'])) {
            // 変更箇所：null渡さないといけない（呼び出し元的に多分これで良いはず？）
            return null;
        }

        return $this->app['session'];
    }
}
