<?php
declare(strict_types=1);

namespace XlsView;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\ServerRequest;

class XlsViewPlugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string
     */
    protected ?string $name = 'XlsView';

    /**
     * Load routes or not
     *
     * @var bool
     */
    protected bool $routesEnabled = false;

    /**
     * Console middleware
     *
     * @var bool
     */
    protected bool $consoleEnabled = false;

    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        /**
         * Add a request detector named "Xml" to check whether the request was for a Xml,
         * either through accept header or file extension
         *
         * @link https://book.cakephp.org/4/en/controllers/request-response.html#checking-request-conditions
         */
        ServerRequest::addDetector(
            'xls',
            [
                'accept' => ['application/vnd.ms-excel'],
                'param' => '_ext',
                'value' => 'xls',
            ]
        );
    }
}
