<?php
/*
 * This file is part of the Goteo Package.
 *
 * (c) Platoniq y Fundación Goteo <fundacion@goteo.org>
 *
 * For the full copyright and license information, please view the README.md
 * and LICENSE files that was distributed with this source code.
 */

namespace Goteo\Controller\Home;

use Symfony\Component\HttpFoundation\Request;

use Goteo\Application\Exception\ControllerAccessDeniedException;
use Goteo\Application\Session;
use Goteo\Application\Config;
use Goteo\Application\View;
use Goteo\Model\Project;
use Goteo\Model\Project\ProjectLocation;
use Goteo\Model\User\UserLocation;
use Goteo\Model\User;

class AjaxHomeController extends \Goteo\Core\Controller {

    public function __construct() {
        // changing to a responsive theme here
        View::setTheme('responsive');
    }

    /**
     * Projects filtered
     */
    public function projectsFilterAction(Request $request)
    {

        if ($request->isMethod('post')) {
            $filter = $request->request->get('filter'); 
            $latitude = $request->request->get('latitude'); 
            $longitude = $request->request->get('longitude'); 

        }

        if($filter=='near')
        {
            // $location=UserLocation::createByIp(null, $request->getClientIp());

            // if($location==false)
                $location=new UserLocation([ 'latitude' => $latitude, 'longitude' => $longitude ]);

            //$projects = ProjectLocation::getNearby(UserLocation::createByIp(null, $request->getClientIp()), 1);
            $projects_locations = ProjectLocation::getNearby($location, 1);

            $projects=[];

            foreach($projects_locations as $distance => $project_location)
            {
                $projects[] = Project::get($project_location->id);
            }
        }

        else
            $projects = Project::published($filter, "Goteo", 0, 33);

        return $this->jsonResponse([
            'filter' => $filter,
            'html' => View::render( 'home/partials/projects_list', ['projects' => $projects] )
        ]);
    }

}