<?php
/**
 * @package midgardmvc_ui_learn
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC documentation display controller
 *
 * @package midgardmvc_ui_learn
 */
class midgardmvc_ui_learn_controllers_documentation
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
        $this->midgardmvc = midgardmvc_core::get_instance();
    }

    private function prepare_component($component)
    {
        $this->data['component'] = $component;
        try
        {
            $this->component = $this->midgardmvc->component->get($this->data['component']);
        }
        catch (Exception $e)
        {
            throw new midgardmvc_exception_notfound("Component {$this->data['component']} not found");
        }
    }

    private function list_directory($path, $prefix = '')
    {
        $files = array
        (
            'name'    => basename($path),
            'label'   => ucfirst(str_replace('_', ' ', basename($path))),
            'folders' => array(),
            'files'   => array(),
        );

        if (!file_exists($path))
        {
            return $files;
        }

        $directory = dir($path);
        while (false !== ($entry = $directory->read()))
        {
            if (substr($entry, 0, 1) == '.')
            {
                // Ignore dotfiles
                continue;
            }

            if (is_dir("{$path}/{$entry}"))
            {
                // List subdirectory
                $files['folders'][$entry] = $this->list_directory("{$path}/{$entry}", "{$prefix}{$entry}/");
                continue;
            }
            
            $pathinfo = pathinfo("{$path}/{$entry}");
            
            if (   !isset($pathinfo['extension'])
                || $pathinfo['extension'] != 'markdown')
            {
                // We're only interested in Markdown files
                continue;
            }
            
            $files['files'][] = array
            (
                'label' => ucfirst(str_replace('_', ' ', $pathinfo['filename'])),
                'path' => "{$prefix}{$pathinfo['filename']}/",
            );
        }
        $directory->close();
        return $files;
    }

    public function get_index(array $args)
    {
        $components = $this->midgardmvc->component->get_components();
        $this->data['components'] = array();
        foreach ($components as $component)
        {
            $component_info = array();
            $component_info['name'] = $component->name;
            $component_info['url'] = $this->midgardmvc->dispatcher->generate_url('midgardmvc_learn_component', array('component' => $component->name), $this->request);
            $this->data['components'][] = $component_info;
        }

        $this->data['contentrepositories'] = array();
        if (extension_loaded('midgard2'))
        {
            $repo = array();
            $repo['name'] = 'Midgard2';
            $repo['url'] = $this->midgardmvc->dispatcher->generate_url('midgardmvc_learn_contentrepository', array('repository' => 'midgard2'), $this->request);
            $this->data['contentrepositories'][] = $repo;
        }
    }

    public function get_component(array $args)
    {
        //$this->midgardmvc->authorization->require_user();
        $this->prepare_component($args['component'], $this->data);

        $this->data['description'] = $this->component->get_description();

        $this->data['files'] = $this->list_directory(MIDGARDMVC_ROOT . "/{$this->data['component']}/documentation");

        $this->data['routes'] = $this->component->get_routes($this->request);
        if ($this->data['routes'])
        {
            $this->data['files']['files'][] = array
            (
                'label' => 'Routes',
                'path' => 'routes/',
            );
        }

        $this->data['classes'] = array();
        $classes = $this->component->get_classes();
        foreach ($classes as $class)
        {
            $class_info = array();
            $class_info['name'] = $class;
            $class_info['url'] = $this->midgardmvc->dispatcher->generate_url('midgardmvc_learn_class', array('class' => $class), $this->request);
            $this->data['classes'][] = $class_info;
        }
    }

    public function get_show(array $args)
    {
        //$this->midgardmvc->authorization->require_user();
        $this->prepare_component($args['variable_arguments'][0], $this->data);
        $path = MIDGARDMVC_ROOT . "/{$this->data['component']}/documentation";
        foreach ($args['variable_arguments'] as $key => $argument)
        {
            if ($key == 0)
            {
                continue;
            }
            
            if ($argument == '..')
            {
                continue;
            }
            
            $path .= "/{$argument}";
        }

        if (   file_exists($path)
            && !is_dir($path))
        {
            // Image or other non-Markdown doc file, pass directly
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mimetype = 'application/octet-stream';
            switch ($extension)
            {
                case 'png':
                    $mimetype = 'image/png';
                    break;
            }
            midgardmvc_core::get_instance()->dispatcher->header("Content-type: {$mimetype}");
            readfile($path);
            die();
        }

        $path .= '.markdown';
        if (!file_exists($path))
        {
            throw new midgardmvc_exception_notfound("File not found");
        }

        require_once MIDGARDMVC_ROOT .'/midgardmvc_core/helpers/markdown.php';
        $this->data['markdown'] = file_get_contents($path);
        $this->data['markdown_formatted'] = Markdown($this->data['markdown']);
    }
    
    public function get_routes(array $args)
    {
        //$this->midgardmvc->authorization->require_user();
        $this->prepare_component($args['component'], $this->data);

        $routes = $this->component->get_routes($this->request);
        
        if (!$routes)
        {
            throw new midgardmvc_exception_notfound("Component {$this->data['component']} has no routes");
        }
        
        $this->data['routes'] = array();
        foreach ($routes as $route)
        {
            $route->controller_url = $this->midgardmvc->dispatcher->generate_url('midgardmvc_learn_class', array('class' => $route->controller), $this->request);
            $route->controller_url .= "#action_{$route->action}";
            $this->data['routes'][] = $route;
        }
    }

    public function get_class(array $args)
    {
        //$this->midgardmvc->authorization->require_user();

        $this->data['class'] = $args['class'];
        if (   !class_exists($this->data['class'])
            && !interface_exists($this->data['class']))
        {
            throw new midgardmvc_exception_notfound("Class {$this->data['class']} not defined");
        }
        
        $reflectionclass = new midgard_reflection_class($this->data['class']);
        $this->data['class_documentation'] = midgardmvc_ui_learn_documentation::get_class_documentation($reflectionclass);

        $this->data['properties'] = midgardmvc_ui_learn_documentation::get_property_documentation($this->data['class']);
        $this->data['signals'] = midgardmvc_ui_learn_documentation::get_signal_documentation($this->data['class']);
 
        $this->data['methods'] = array();
        $this->data['abstract_methods'] = array(); 
        $this->data['static_methods'] = array(); 
        $reflectionmethods = $reflectionclass->getMethods();
        foreach ($reflectionmethods as $method)
        {
            $method_docs = midgardmvc_ui_learn_documentation::get_method_documentation($this->data['class'], $method->getName());
            if (isset($method_docs['abstract']))
            {
                $this->data['abstract_methods'][] = $method_docs;
                continue;
            }
            elseif (isset($method_docs['static']))
            {
                $this->data['static_methods'][] = $method_docs;
                continue;
            }
            $this->data['methods'][] = $method_docs;
        }
    }
}
?>
