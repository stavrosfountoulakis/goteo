<?php

namespace Goteo\Controller\Dashboard {

    use Goteo\Model,
		Goteo\Library\Text,
		Goteo\Library\Feed,
		Goteo\Library\Mail,
		Goteo\Library\Template,
		Goteo\Library\Message;

/*
 * las opciones para /dashboard/projects:
 * 
 *      'updates' actualizaciones
 *      'supports' editar colaboraciones
 *      'widgets' ofrece el código para poner su proyecto en otras páginas (vertical y horizontal)
 *      'licencia' el acuerdo entre goteo y el usuario, licencia cc-by-nc-nd, enlace al pdf
 *      'gestionar retornos' resumen recompensas/cofinanciadores/conseguido  y lista de cofinanciadores y recompensas esperadas
 *      'messegers' gestionar colaboradores
 *      'contract' contrato
 *      'account'  cuentas
 */            
    class Projects {
            
        /**
         * Gestiona las acciones de gestión de updates
         * 
         * @param type $action (por referencia)
         * @param type $id del post a gestionar
         * @param type $blog id del blog del proyecto
         * @param type $errors (por referencia)
         * @return instancia de Post para las acciones add y edit , array de Posts para las acciones delete y list
         */
        public static function prepare_updates (&$action, $id, $blog) {
            // segun la accion
            switch ($action) {
                case 'none' :
                    $posts = array();
                    break;
                case 'add':
                    $post = new Model\Blog\Post(
                                    array(
                                        'blog' => $blog,
                                        'date' => date('Y-m-d'),
                                        'publish' => false,
                                        'allow' => true
                                    )
                    );
                    return array($post, null);
                    
                    break;
                case 'edit':
                    if (empty($id)) {
                        Message::Error(Text::get('dashboard-project-updates-nopost'));
                        $action = 'list';
                        break;
                    } else {
                        $post = Model\Blog\Post::get($id);

                        if (!$post instanceof Model\Blog\Post) {
                            Message::Error(Text::get('dashboard-project-updates-postcorrupt'));
                            $action = 'list';
                            break;
                        }
                        return array($post, null);
                    }

                    break;
                case 'delete':
                    $post = Model\Blog\Post::get($id);
                    if ($post->delete($id)) {
                        Message::Info(Text::get('dashboard-project-updates-deleted'));
                    } else {
                        Message::Error(Text::get('dashboard-project-updates-delete_fail'));
                    }
                    $posts = Model\Blog\Post::getAll($blog, null, false);
                    $action = 'list';
                    return array(null, $posts);

                    break;
                default:
                    $posts = Model\Blog\Post::getAll($blog, null, false);
                    $action = 'list';
                    return array(null, $posts);

                    break;
            }
        }
        
        
        /**
         * Realiza el envio masivo a participantees o cofinanciadores
         * 
         * @param type $option 'messegers' || 'rewards'
         * @param type $project Instancia del proyecto de trabajo
         * @return boolean
         */
        public static function process_mailing ($option, $project) {
            $who = array();

            // verificar que hay mensaje
            if (empty($_POST['message'])) {
                Message::Error(Text::get('dashboard-investors-mail-text-required'));
                return false;
            } else {
                $msg_content = nl2br(\strip_tags($_POST['message']));
            }

            // si a todos los participantes
            if ($option == 'messegers' && !empty($_POST['msg_all'])) {
                // a todos los participantes
                foreach (Model\Message::getMessegers($project->id) as $messeger => $msgData) {
                    if ($messeger == $project->owner)
                        continue;
                    $who[$messeger] = $messeger;
                    unset($msgData); // los datos del mensaje del participante no se usan
                }
            } elseif ($option == 'rewards' && !empty($_POST['msg_all'])) {
                // a todos los cofinanciadores
                foreach (Model\Invest::investors($project->id, false, true) as $user => $investor) {
                    if (!in_array($user, $who)) {
                        $who[$user] = $investor->user;
                    }
                }
            } elseif (!empty($_POST['msg_user'])) {
                // a usuario individual
                $who[$_POST['msg_user']] = $_POST['msg_user'];
            } elseif ($option == 'rewards') {
                $msg_rewards = array();
                // estos son msg_reward-[rewardId], a un grupo de recompensa
                foreach ($_POST as $key => $value) {
                    $parts = explode('-', $key);
                    if ($parts[0] == 'msg_reward' && $value == 1) {
                        $msg_rewards[] = $parts[1];
                    }
                }

                // para cada recompensa
                foreach ($msg_rewards as $reward) {
                    foreach (Model\Invest::choosed($reward) as $user) {
                        $who[$user] = $user;
                    }
                }
            }

            // no hay destinatarios
            if (count($who) == 0) {
                Message::Error(Text::get('dashboard-investors-mail-nowho'));
                return false;
            }

            // obtener contenido
            // segun destinatarios
            $allsome = explode('/', Text::get('regular-allsome'));
            $enviandoa = !empty($_POST['msg_all']) ? $allsome[0] : $allsome[1];
            if ($option == 'messegers') {
                Message::Info(Text::get('dashboard-messegers-mail-sendto', $enviandoa));
            } else {
                Message::Info(Text::get('dashboard-investors-mail-sendto', $enviandoa));
            }

            // Obtenemos la plantilla para asunto y contenido
            $template = Template::get(2);

            // Sustituimos los datos
            if (!empty($_POST['subject'])) {
                $subject = $_POST['subject'];
            } else {
                $subject = str_replace('%PROJECTNAME%', $project->name, $template->title);
            }

            $remite = $project->name . ' ' . Text::get('regular-from') . ' ';
            $remite .= (NODE_ID != GOTEO_NODE) ? NODE_NAME : GOTEO_MAIL_NAME;

            $search = array('%MESSAGE%', '%PROJECTNAME%', '%PROJECTURL%', '%OWNERURL%', '%OWNERNAME%');
            $replace = array($msg_content, $project->name, SITE_URL . "/project/" . $project->id,
                SITE_URL . "/user/profile/" . $project->owner, $project->user->name);
            $content = \str_replace($search, $replace, $template->text);

            foreach ($who as $userId) {
                $errors = array();
                //me cojo su email y lo meto en un array para enviar solo con una instancia de Mail
                $userData = Model\User::getMini($userId);

                // iniciamos el objeto mail
                $mailHandler = new Mail();
                $mailHandler->fromName = $remite;
                $mailHandler->to = $userData->email;
                $mailHandler->toName = $userData->name;
                // blind copy a goteo desactivado durante las verificaciones
//                                    $mailHandler->bcc = 'comunicaciones@goteo.org';
                $mailHandler->subject = $subject;
                $mailHandler->content = str_replace('%NAME%', $userData->name, $content);
                // esto tambien es pruebas
                $mailHandler->html = true;
                $mailHandler->template = $template->id;
                if ($mailHandler->send($errors)) {
                    Message::Info(Text::get('dashboard-investors-mail-sended', $userData->name));
                } else {
                    Message::Error(Text::get('dashboard-investors-mail-fail', $userData->name) . ' : ' . implode(', ', $errors));
                }

                unset($mailHandler);
                unset($userData);
            }
            
            unset($who);
            
            return true;
        }
        
        
        /**
         * Graba el contrato con lo recibido por POST
         * 
         * @param object $project Instancia de proyecto de trabajo
         * @param array $errors (por referncia)
         * @return boolean
         */
        public static function process_contract ($project, &$errors = array()) {

            $contract = Model\Contract::get($project->id);

            foreach ($_POST as $key => $value) {
                if (isset($contract->$key)) {
                    $contract->$key = $value;
                }
            }

            if ($contract->save($errors)) {
                Message::Info('Datos de contrato actualizados');

                // si el impulsor da los datos por cerrados hacemos un feed para admin
                if (!empty($_POST['close_owner'])) {
                    $contract->setStatus('owner', true);

                    // Evento Feed
                    $log = new Feed();
                    $log->setTarget($project->id);
                    $log->populate('usuario cambia los datos del contrato de su proyecto (dashboard)', '/admin/projects', \vsprintf('%s ha modificado los datos del contrato del proyecto %s', array(
                                Feed::item('user', $_SESSION['user']->name, $_SESSION['user']->id),
                                Feed::item('project', $project->name, $project->id)
                            )));
                    $log->doAdmin('user');
                    unset($log);
                    
                    //@TODO: marcar en el registro de gestión, "datos de contrato" cerrados
                }
                
                return true;
                
            } else {
                Message::Error('Ha habido algún error al grabar los datos de contrato');
                return false;
            }

        }
        
        
        /**
         * Graba las cuentas con lo recibido por POST
         * 
         * @param object $project Instancia de proyecto de trabajo
         * @param array $errors (por referncia)
         * @return boolean
         */
        public static function process_account ($project, &$errors = array()) {
            $accounts = Model\Project\Account::get($project->id);
            $accounts->bank = $_POST['bank'];
            $accounts->bank_owner = $_POST['bank_owner'];
            $accounts->paypal = $_POST['paypal'];
            $accounts->paypal_owner = $_POST['paypal_owner'];
            if ($accounts->save($errors)) {

                Message::Info('Cuentas actualizadas');

                // Evento Feed
                $log = new Feed();
                $log->setTarget($project->id);
                $log->populate('usuario cambia las cuentas de su proyecto (dashboard)', '/admin/projects', \vsprintf('%s ha modificado la cuenta bancaria/paypal del proyecto %s', array(
                            Feed::item('user', $_SESSION['user']->name, $_SESSION['user']->id),
                            Feed::item('project', $project->name, $project->id)
                        )));
                $log->doAdmin('user');
                unset($log);
            } else {
                Message::Error('Ha habido algún error al grabar los datos de cuentas');
                return false;
            }
        }
        
        
        /**
         * Graba las colaboraciones con lo recibido por POST
         * 
         * @param object $project Instancia de proyecto de trabajo
         * @param array $errors (por referncia)
         * @return object $project Instancia de proyecto modificada
         */
        public static function process_support ($project, &$errors = array()) {
            // tratar colaboraciones existentes
            foreach ($project->supports as $key => $support) {

                // quitar las colaboraciones marcadas para quitar
                if (!empty($_POST["support-{$support->id}-remove"])) {
                    unset($project->supports[$key]);
                    continue;
                }

                if (isset($_POST['support-' . $support->id . '-support'])) {
                    $support->support = $_POST['support-' . $support->id . '-support'];
                    $support->description = $_POST['support-' . $support->id . '-description'];
                    $support->type = $_POST['support-' . $support->id . '-type'];

                    if (!empty($support->thread)) {
                        // actualizar ese mensaje
                        $msg = Model\Message::get($support->thread);
                        $msg->date = date('Y-m-d');
                        $msg->message = "{$support->support}: {$support->description}";
                        $msg->blocked = true;
                        $msg->save();
                    } else {
                        // grabar nuevo mensaje
                        $msg = new Model\Message(array(
                                    'user' => $project->owner,
                                    'project' => $project->id,
                                    'date' => date('Y-m-d'),
                                    'message' => "{$support->support}: {$support->description}",
                                    'blocked' => true
                                ));
                        if ($msg->save()) {
                            // asignado a la colaboracion como thread inicial
                            $support->thread = $msg->id;

                            // Evento Feed
                            $log = new Feed();
                            $log->setTarget($project->id);
                            $log->populate('usuario pone una nueva colaboracion en su proyecto (dashboard)', '/admin/projects', \vsprintf('%s ha publicado una nueva %s en el proyecto %s, con el título "%s"', array(
                                        Feed::item('user', $_SESSION['user']->name, $_SESSION['user']->id),
                                        Feed::item('message', 'Colaboración'),
                                        Feed::item('project', $project->name, $project->id),
                                        Feed::item('update', $support->support, $project->id . '/messages#message' . $msg->id)
                                    )));
                            $log->doAdmin('user');

                            // evento público, si el proyecto es público
                            if ($project->status > 2) {
                                $log->populate($_SESSION['user']->name, '/user/profile/' . $_SESSION['user']->id, Text::html('feed-new_support', Feed::item('project', $project->name, $project->id), Feed::item('update', $support->support, $project->id . '/messages#message' . $msg->id)
                                        ), $_SESSION['user']->avatar->id);
                                $log->doPublic('community');
                            }
                            unset($log);
                        }
                    }
                }
            }

            // añadir nueva colaboracion (no hacemos lo del mensaje porque esta sin texto)
            if (!empty($_POST['support-add'])) {

                $new_support = new Model\Project\Support(array(
                            'project' => $project->id,
                            'support' => 'Nueva colaboración',
                            'type' => 'task',
                            'description' => ''
                        ));

                if ($new_support->save($errors)) {

                    $project->supports[] = $new_support;
                    $_POST['support-' . $new_support->id . '-edit'] = true;
                } else {
                    $project->supports[] = new Model\Project\Support(array(
                                'project' => $project->id,
                                'support' => 'Nueva colaboración',
                                'type' => 'task',
                                'description' => ''
                            ));
                }
            }

            // guardamos los datos que hemos tratado y los errores de los datos
            $project->save($errors);
            
            return $project;
        }
        
        
        /**
         * Graba un registro de novedad con lo recibido por POST
         * 
         * @param array  $action (add o edit) y $id del post
         * @param object $project Instancia de proyecto de trabajo
         * @param array $errors (por referncia)
         * @return array $action por si se queda editando o sale a la lista y $id por si es un add y se queda editando
         */
        public static function process_updates ($action, $project, &$errors = array()) {
            

            $editing = false;

            if (!empty($_POST['id'])) {
                $post = Model\Blog\Post::get($_POST['id']);
            } else {
                $post = new Model\Blog\Post();
            }
            // campos que actualizamos
            $fields = array(
                'id',
                'blog',
                'title',
                'text',
                'image',
                'media',
                'legend',
                'date',
                'publish',
                'allow'
            );

            foreach ($fields as $field) {
                $post->$field = $_POST[$field];
            }

            // tratar la imagen y ponerla en la propiedad image
            if (!empty($_FILES['image_upload']['name'])) {
                $post->image = $_FILES['image_upload'];
                $editing = true;
            }

            // tratar las imagenes que quitan
            foreach ($post->gallery as $key => $image) {
                if (!empty($_POST["gallery-{$image->id}-remove"])) {
                    $image->remove('post');
                    unset($post->gallery[$key]);
                    if ($post->image == $image->id) {
                        $post->image = '';
                    }
                    $editing = true;
                }
            }

            if (!empty($post->media)) {
                $post->media = new Model\Project\Media($post->media);
            }

            // el blog de proyecto no tiene tags?Â¿?
            // $post->tags = $_POST['tags'];
            /// este es el único save que se lanza desde un metodo process_
            if ($post->save($errors)) {
                $id = $post->id;
                if ($action == 'edit') {
                    Message::Info(Text::get('dashboard-project-updates-saved'));
                } else {
                    Message::Info(Text::get('dashboard-project-updates-inserted'));
                }
                $action = $editing ? 'edit' : 'list';

                // si ha marcado publish, grabamos evento de nueva novedad en proyecto
                if ((bool) $post->publish) {
                    // Evento Feed
                    $log = new Feed();
                    $log->setTarget($project->id);
                    $log->populate('usuario publica una novedad en su proyecto (dashboard)', '/project/' . $project->id . '/updates/' . $post->id, 
                            \vsprintf('%s ha publicado un nuevo post en %s sobre el proyecto %s, con el título "%s"', array(
                                Feed::item('user', $_SESSION['user']->name, $_SESSION['user']->id),
                                Feed::item('blog', Text::get('project-menu-updates')),
                                Feed::item('project', $project->name, $project->id),
                                Feed::item('update', $post->title, $project->id . '/updates/' . $post->id)
                            )));
                    $log->unique = true;
                    $log->doAdmin('user');

                    // evento público
                    $log->populate($post->title, '/project/' . $project->id . '/updates/' . $post->id, Text::html('feed-new_update', Feed::item('user', $_SESSION['user']->name, $_SESSION['user']->id), Feed::item('blog', Text::get('project-menu-updates')), Feed::item('project', $project->name, $project->id)
                            ), $post->gallery[0]->id);
                    $log->doPublic('projects');

                    // si no ha encontrado otro, lanzamos el update
                    if (!$log->unique_issue) {
                        \Goteo\Controller\Cron::toInvestors('update', $project, $post);
                    }

                    unset($log);
                }
            } else {
                $errors[] = Text::get('dashboard-project-updates-fail');
            }

            return array($action, $id);
            
        }
        
        
    }

}
