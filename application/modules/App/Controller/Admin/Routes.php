<?php

namespace App\Controller\Admin;

use Micro\Application\Controller;
use Micro\Acl\AuthorizeInterface;
use Micro\Http\Response\RedirectResponse;

class Routes extends Controller implements AuthorizeInterface
{
    public $scope = 'admin';

    public function indexAction()
    {
        $routerConfig = $this->container->get('config')->get('router');

        if (!isset($routerConfig['routes'])) {
            $routerConfig['routes'] = [];
        }

        if ($this->request->isPost()) {

            $data = $this->request->getPost('routes', []);

            foreach ($data as $k => $v) {
                if (isset($routerConfig['routes'][$k])) {
                    $data[$k] = $v + $routerConfig['routes'][$k];
                }
            }

            $routerConfig['routes'] = $data;

            file_put_contents(data_path('routes.php'), '<?php' . PHP_EOL . 'return ' . $this->var_export_short($routerConfig, true) . ';');

            return (new RedirectResponse(route()))->withFlash('Информацията е записана');
        }


        return [
            'routes' => $routerConfig['routes'],
        ];
    }

    public function ajaxReorderAction()
    {
        return '';
    }

    public function var_export_short($data, $return = true)
    {
        $dump = var_export($data, true);

        $dump = preg_replace('#(?:\A|\n)([ ]*)array \(#i', '[', $dump); // Starts
        $dump = preg_replace('#\n([ ]*)\),#', "\n$1],", $dump); // Ends
        $dump = preg_replace('#=> \[\n\s+\],\n#', "=> [],\n", $dump); // Empties

        if (gettype($data) == 'object') { // Deal with object states
            $dump = str_replace('__set_state(array(', '__set_state([', $dump);
            $dump = preg_replace('#\)\)$#', "])", $dump);
        } else {
            $dump = preg_replace('#\)$#', "]", $dump);
        }

        if ($return === true) {
            return $dump;
        } else {
            echo $dump;
        }
    }
}
