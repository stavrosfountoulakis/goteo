<?php

namespace Goteo\Controller\Admin {

    use Goteo\Core\View,
        Goteo\Core\Redirection,
        Goteo\Core\Error,
		Goteo\Model\Node,
		Goteo\Library\Feed,
		Goteo\Library\Template,
		Goteo\Library\Mail;

    class Sended {

        public static function process ($action = 'list', $id = null, $filters = array()) {

            $templates = Template::getAllMini();
            $nodes = Node::getList();

            $sended = Mail::getSended($filters, $_SESSION['admin_node'], 99);

            return new View(
                'view/admin/index.html.php',
                array(
                    'folder' => 'sended',
                    'file' => 'list',
                    'filters' => $filters,
                    'templates' => $templates,
                    'nodes' => $nodes,
                    'sended' => $sended
                )
            );
            
        }

    }

}
