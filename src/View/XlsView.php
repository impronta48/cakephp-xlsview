<?php
declare(strict_types=1);

namespace XlsView\View;

use Cake\Core\Exception\CakeException;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;
use Cake\View\SerializedView;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

/**
 * A view class that is used for XLS responses.
 *
 * By setting the 'serialize' view builder option, you can specify a view variable
 * that should be serialized to XLS and used as the response for the request.
 * This allows you to omit templates + layouts, if your just need to emit a single view
 * variable as the XLS response.
 *
 * In your controller, you could do the following:
 *
 * `$this->set(['posts' => $posts])->viewBuilder()->setOption('serialize', 'posts');`
 *
 * When the view is rendered, the `$posts` view variable will be serialized
 * into XLS.
 *
 * When rendering the data, the data should be a single, flat array. If this is not the case,
 * then you should also specify the `extract` view option:
 *
 * ```
 * $extract = [
 *   ['id', '%d'],       // Hash-compatible path, sprintf-compatible format
 *   'description',     // Hash-compatible path
 *   function ($row) {  // Callable
 *      //return value
 *   }
 * ];
 * ```
 *
 * You can also define `serialize` as an array. This will create a top level object containing
 * all the named view variables:
 *
 * ```
 * $this->set(compact('posts', 'users', 'stuff'));
 * $this->viewBuilder()->setOption('serialize', ['posts', 'users']);
 * ```
 *
 * Each of the view vars in `serialize` would then be output into the XLS output.
 *
 * If you don't use the `serialize` option, you will need a view. You can use extended
 * views to provide layout like functionality.
 *
 * When not using custom views, you may specify the following view options:
 *
 * - array `header`: (default null)    A flat array of header column names
 * - array `footer`: (default null)    A flat array of footer column names
 *
 * @link https://github.com/impronta48/cakephp-XlsView
 */
class XlsView extends SerializedView
{
    /**
     * XLS layouts are located in the xls sub directory of `Layouts/`
     *
     * @var string
     */
    protected string $layoutPath = 'xls';

    /**
     * XLS views are always located in the 'xls' sub directory for a
     * controllers views.
     *
     * @var string
     */
    protected string $subDir = 'xls';

    /**
     * Default config.
     *
     * - 'header': (default null)  A flat array of header column names
     * - 'footer': (default null)  A flat array of footer column names
     * - 'extract': (default null) An array of Hash-compatible paths or
     *     callable with matching 'sprintf' $format as follows:
     *     $extract = [
     *         [$path, $format],
     *         [$path],
     *         $path,
     *         function () { ... } // Callable
     *      ];
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'extract' => null,
        'footer' => null,
        'header' => null,
        'serialize' => null,
        ];

    /**
     * The sheet object where we are writing
     *
     * @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
     */
    private Worksheet $sheet;

    /**
     * Initalize View
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Mime-type this view class renders as.
     *
     * @return string The XLS content type.
     */
    public static function contentType(): string
    {
        return 'application/vnd.ms-excel';
    }

    /**
     * Return Columns for the current dataset
     *
     * @param array|string $serialize The name(s) of the view variable(s) that
     *   need(s) to be serialized
     * @return array The columns
     */
    private function _getColumns(array|string $serialize): array
    {
        $columns = $this->getConfig('header');
        if ($columns === null) {
            $columns = array_keys($this->viewVars[$serialize]);
        }

        return $columns;
    }

    /**
     * Serialize view vars.
     *
     * @param array|string $serialize The name(s) of the view variable(s) that
     *   need(s) to be serialized
     * @return string The serialized data or false.
     */
    protected function _serialize(array|string $serialize): string
    {
        $spreadsheet = new Spreadsheet();
        $this->sheet = $spreadsheet->getActiveSheet();

        $columns = $this->_getColumns($serialize);

        $row = '1';
        $col = 'A';
        foreach ($columns as $c) {
                $this->sheet->setCellValue("$col$row", $c);
                $col++;
        }
        $this->_renderContent();

        $writer = new Xls($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $output = ob_get_contents();

        //Free memory
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        //Return String
        return $output;
    }

    /**
     * Renders the body of the data to the xls
     *
     * @return void
     * @throws \Cake\Core\Exception\CakeException
     */
    protected function _renderContent(): void
    {
        $extract = $this->getConfig('extract');
        $serialize = $this->getConfig('serialize');

        if ($serialize === true) {
            $serialize = array_keys($this->viewVars);
        }

        $row = 1;
        foreach ((array)$serialize as $viewVar) {
            $row++;
            if (is_scalar($this->viewVars[$viewVar])) {
                throw new CakeException("'" . $viewVar . "' is not an array or iterable object.");
            }

            foreach ($this->viewVars[$viewVar] as $_data) {
                if ($_data instanceof EntityInterface) {
                    $_data = $_data->toArray();
                }

                if ($extract === null) {
                    $this->_renderRow($_data, $row);
                    continue;
                }

                $values = [];
                foreach ($extract as $formatter) {
                    if (!is_string($formatter) && is_callable($formatter)) {
                        $value = $formatter($_data);
                    } else {
                        $path = $formatter;
                        $format = null;
                        if (is_array($formatter)) {
                            [$path, $format] = $formatter;
                        }

                        if (!str_contains($path, '.')) {
                            $value = $_data[$path];
                        } else {
                            $value = Hash::get($_data, $path);
                        }

                        if ($format) {
                            $value = sprintf($format, $value);
                        }
                    }

                    $values[] = $value;
                }
                $this->_renderRow($values, $row);
            }
        }
    }

    /**
     * Aggregates the rows into a single XLS
     *
     * @param array<string>|null $rowData Row data
     * @return string CSV with all data to date
     */
    protected function _renderRow(?array $rowData = null, int $rowNum): void
    {
        $serialize = $this->getConfig('serialize');
        $columns = $this->_getColumns($serialize);
        $col = 'A';

        foreach ($columns as $c) {
            $value = $rowData[$c] ?? '';
            $this->sheet->setCellValue("$col$rowNum", $value);
            $col++;
        }
    }
}
