<?php
/**
 * @package midgardmvc_ui_learn
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Midgard MVC documentation system
 *
 * @package midgardmvc_ui_learn
 */
class midgardmvc_ui_learn_injector
{
    public function inject_process(midgardmvc_core_request $request)
    {
        // We inject the process only to register our own URL handlers
        $request->add_component_to_chain(midgardmvc_core::get_instance()->component->get('midgardmvc_ui_learn'));
    }
}
?>
