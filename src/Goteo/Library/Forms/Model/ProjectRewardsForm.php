<?php

/*
 * This file is part of the Goteo Package.
 *
 * (c) Platoniq y Fundación Goteo <fundacion@goteo.org>
 *
 * For the full copyright and license information, please view the README.md
 * and LICENSE files that was distributed with this source code.
 */

namespace Goteo\Library\Forms\Model;

use Goteo\Library\Forms\FormProcessorInterface;
use Goteo\Library\Forms\AbstractFormProcessor;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints;
use Goteo\Library\Text;
use Goteo\Library\Currency;
use Goteo\Model\Project\Reward;
use Goteo\Library\Forms\FormModelException;

class ProjectRewardsForm extends AbstractFormProcessor implements FormProcessorInterface {
    private $rewards = [];

    public function delReward($id) {
        unset($this->rewards[$id]);
        $this->getBuilder()
            ->remove("amount_$id")
            ->remove("icon_$id")
            ->remove("reward_$id")
            ->remove("description_$id")
            ->remove("remove_$id")
            ;
    }

    public function addReward(Reward $reward) {
        $project = $this->getModel();
        $this->rewards[$reward->id] = $reward;
        $suffix = "_{$reward->id}";
        $this->getBuilder()
            ->add("amount$suffix", 'number', [
                'label' => 'rewards-field-individual_reward-amount',
                'data' => $reward->amount,
                // 'pre_addon' => '<i class="fa fa-money"></i>',
                'pre_addon' => Currency::get($project->currency, 'html'),
                // 'post_addon' => Currency::get($project->currency, 'name'),
                'constraints' => array(new Constraints\NotBlank()),
                'required' => false,
            ])
            ->add("units$suffix", 'number', [
                'label' => 'rewards-field-individual_reward-units',
                'data' => $reward->amount,
                'pre_addon' => '#',
                'constraints' => array(new Constraints\NotBlank()),
                'required' => false,
            ])
            // ->add("icon$suffix", 'choice', [
            //     'label' => 'rewards-field-icon',
            //     'data' => $reward->icon,
            //     'choices' => Reward::icons('individual'),
            //     'constraints' => array(new Constraints\NotBlank()),
            //     'required' => true,
            // ])
            ->add("reward$suffix", 'text', [
                'label' => 'rewards-field-individual_reward-reward',
                'data' => $reward->reward,
                'constraints' => array(new Constraints\NotBlank()),
                'required' => false,
            ])
            ->add("description$suffix", 'textarea', [
                'label' => 'rewards-field-individual_reward-description',
                'data' => $reward->description,
                'required' => false,
            ])
            ->add("remove$suffix", 'submit', [
                'label' => Text::get('regular-delete'),
                'icon_class' => 'fa fa-trash',
                'span' => 'hidden-xs',
                'attr' => [
                    'class' => 'pull-right btn btn-default remove-reward',
                    'data-confirm' => Text::get('project-remove-reward-confirm')
                    ]
            ]);
    }

    public function createForm() {
        $project = $this->getModel();
        $builder = $this->getBuilder()
            // ->add('title-rewards', 'title', ['label' => 'rewards-fields-individual_reward-title'])
            ;
        foreach($project->individual_rewards as $reward) {
            $this->addReward($reward);
        }

        return $this;
    }

    public function save(FormInterface $form = null) {
        if(!$form) $form = $this->getBuilder()->getForm();

        $data = array_intersect_key($form->getData(), $form->all());
        // print_r($data);die;
        $project = $this->getModel();
        $project->one_round = (bool) $data['one_round'];

        $errors = [];
        $ids = [];

        foreach($data as $key => $val) {
            list($field, $id) = explode('_', $key);
            if(!in_array($field, ['amount', 'icon', 'required', 'reward', 'description'])) continue;
            $ids[$id] = $id;

            $reward = $this->rewards[$id];
            $reward->{$field} = $val;
        }

        // Check if we want to remove a reward
        $validate = true;
        foreach($ids as $id) {
            if($form->get("remove_$id")->isClicked()) {
                $this->delReward($id);
                $validate = false;
            }
        }

        // Validate form here to avoid deleted elements
        if($validate && !$form->isValid()) throw new FormModelException(Text::get('form-has-errors'));

        // Add reward
        if($form['add-reward']->isClicked()) {
            $reward = new Reward(['project' => $project->id, 'type' => 'individual']);
            if(!$reward->save($errors)) {
                throw new FormModelException(Text::get('form-sent-error', implode(', ',$errors)));
            }
            $this->addReward($reward);
        }

        $project->individual_rewards = $this->rewards;
        // var_dump($project->rewards);die;
        if (!$project->save($errors)) {
            throw new FormModelException(Text::get('form-sent-error', implode(', ',$errors)));
        }

        return $this;
    }

}
