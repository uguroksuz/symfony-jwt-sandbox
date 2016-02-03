<?php

namespace App\AdminBundle\Admin\User;

use App\AdminBundle\Admin\AbstractAdmin;
use FOS\UserBundle\Model\UserManagerInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class BaseUserAdmin extends AbstractAdmin
{
    protected $userManager;

    public $realLabel;

    /**
     * Default values to the datagrid.
     *
     * @var array
     */
    protected $datagridValues = array(
        '_page'       => 1,
        '_sort_order' => 'DESC',
        '_sort_by'    => 'createdAt',
    );

    /**
     * {@inheritdoc}
     */
    public function getFormBuilder()
    {
        $this->formOptions['data_class'] = $this->getClass();

        $options = $this->formOptions;
        $options['validation_groups'] = (!$this->getSubject() || is_null($this->getSubject()->getId())) ? 'Registration' : 'Profile';

        $formBuilder = $this->getFormContractor()->getFormBuilder($this->getUniqid(), $options);

        $this->defineFormBuilder($formBuilder);

        return $formBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getExportFields()
    {
        // avoid security field to be exported
        return array_filter(parent::getExportFields(), function ($v) {
            return !in_array($v, array(
                'password', 'salt', 'usernameCanonical', 'emailCanonical', 'locked',    'expired',    'expiresAt',    'confirmationToken',    'passwordRequestedAt',    'roles',    'credentialsExpired',    'credentialsExpireAt', 'createdAt',    'updatedAt', 'lastLogin',
            ));
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('id', null, array('label' => 'Id'))
            ->addIdentifier('email')
            ->add('group', null, array('label' => 'Groupe'))
            ->add('firstname', null, array('label' => 'Prénom'))
            ->add('lastname', null, array('label' => 'Nom'))
            ->add('address', null, array('label' => 'Adresse'))
            ->add('zipcode', null, array('label' => 'Code postal'))
            ->add('phone', null, array('label' => 'Téléphone'))
            ->add('createdAt', 'date', array('label' => 'Créé le', 'format' => 'd/m/Y'))
            ->add('_action', 'actions', [
                'actions' => array(
                    'show'   => [],
                    'edit'   => [],
                    'delete' => [],
                ),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $filterMapper)
    {
        $filterMapper
            ->add('id')
            ->add('email')
            ->add('lastname', null, array('label' => 'Nom'))
            ->add('zipcode', null, array('label' => 'Code postal'))
            ->add('group')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->with('General')
                ->add('email')
            ->end()
            ->with('Profile')
                ->add('dateOfBirth')
                ->add('firstname')
                ->add('lastname')
                ->add('gender')
                ->add('phone')
            ->end()
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $container = $this->getContainer();
        $roles = $container->getParameter('security.role_hierarchy.roles');
        $rolesChoices = self::flattenRoles($roles);
        /* Custom check displaying profile picture if is it */
        $pictureOptions =  array(
            'required'   => false,
            'data_class' => null,
            'label'      => 'Photo de profil',
        );
        if ($this->getSubject()->getId()) {
            $subject = $this->getSubject();
            if ($subject->getPicture()) {
                $path = sprintf('http://%s/bundles/appuser/pictures/%s', $container->getParameter('domain'), $subject->getPicture());
            } else {
                $path = sprintf('http://%s/bundles/appuser/pictures/default.jpg', $container->getParameter('domain'));
            }
            $pictureOptions['help'] = sprintf('<div class="icon_prev"><img src="%s"/></div>', $path);
        }
        /* End custom check */
        $formMapper
            ->with('Profil')
                ->add('firstname', null, array('required' => false, 'label' => 'Prénom'))
                ->add('lastname', null, array('required' => false, 'label' => 'Nom'))
                ->add('gender', 'sonata_user_gender', array(
                    'required'           => false,
                    'label'              => 'Sexe',
                    'translation_domain' => $this->getTranslationDomain(),
                ))
                ->add('dateOfBirth', 'sonata_type_date_picker', array(
                    'label'       => 'Date de naissance',
                    'format'      => 'dd/MM/yyyy',
                    'dp_language' => 'fr',
                    'required'    => false,
                ))
                ->add('file', 'file', $pictureOptions)
                ->add('description', 'textarea', array(
                    'attr' => array(
                        'maxlength' => 500,
                    ),
                    'required' => false,
                    'label'    => 'Déscription',
                ))
                ->add('phone', null, array('required' => false, 'label' => 'Téléphone'))
                ->add('address', 'textarea', array(
                    'label'    => 'Adresse',
                    'required' => false,
                    'attr'     => array(
                      'maxlength' => 500,
                    ),
                ))
                ->add('city', null, array(
                    'label'    => 'Ville',
                    'required' => false,
                ))
                ->add('zipcode', null, array(
                    'label'    => 'Code postal',
                    'required' => false,
                ))
            ->end()
            ->with('Sports')
                ->add('sportUsers', 'sonata_type_collection', array(
                    'by_reference' => false,
                    'required'     => false,
                    'label'        => false,
                ), array(
                    'edit'       => 'inline',
                    'inline'     => 'table',
                    'admin_code' => 'app_admin.admin.sport_user',
                ))
            ->end()
            ->with('Accès')
                ->add('email')
                ->add('plainPassword', 'password', array(
                    'label'    => 'Mot de passe',
                    'required' => (!$this->getSubject() || is_null($this->getSubject()->getId())),
                ))
            ->end()
        ;

        if ($this->getSubject() && !$this->getSubject()->hasRole('ROLE_SUPER_ADMIN')) {
            $formMapper
                ->with('Gestion')
                    ->add('realRoles', 'choice', array(
                        'label'    => 'Rôles',
                        'choices'  => $rolesChoices,
                        'multiple' => true,
                        'required' => false,
                    ))
                    ->add('locked', null, array('required' => false))
                    ->add('enabled', null, array('required' => false))
                ->end()
            ;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate($user)
    {
        $user->setUpdatedAt(new \DateTime());
        $user->setUsername($user->getEmail());
        $this->getUserManager()->updateCanonicalFields($user);
        $this->getUserManager()->updatePassword($user);

        $uploadPath = $this->locateResource('@AppUserBundle/Resources/public/pictures');

        if ($user->getFile()) {
            $user->uploadPicture($uploadPath);
        }
    }

    public function prePersist($user)
    {
        $user->setCreatedAt(new \DateTime());
        $user->setUsername($user->getEmail());
        $uploadPath = $this->locateResource('@AppUserBundle/Resources/public/pictures');

        if ($user->getFile()) {
            $user->uploadPicture($uploadPath);
        }

        return $user;
    }

    /**
     * @param UserManagerInterface $userManager
     */
    public function setUserManager(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * @return UserManagerInterface
     */
    public function getUserManager()
    {
        return $this->userManager;
    }

    /**
     * Turns the role's array keys into string <ROLES_NAME> keys.
     */
    protected static function flattenRoles($rolesHierarchy)
    {
        $flatRoles = array();
        foreach ($rolesHierarchy as $roles) {
            if (empty($roles)) {
                continue;
            }

            foreach ($roles as $role) {
                if (!isset($flatRoles[$role]) && $role !== 'ROLE_USER') {
                    $flatRoles[$role] = $role;
                }
            }
        }

        return $flatRoles;
    }

    /**
     * Get the user's group of the current admin class.
     *
     * @return string
     */
    protected function getUserGroup()
    {
        $group = $this->get('doctrine')
            ->getRepository('AppUserBundle:Group')
            ->findOneBy(array(
                'name' => $this->getLabel(),
            ))
        ;

        return $group;
    }

    /**
     * Get the user's group of the current admin class.
     *
     * @return string
     */
    public function getRealLabel()
    {
        return $this->realLabel;
    }

    public function setRealLabel($label)
    {
        $this->realLabel = $label;

        return $this;
    }

    /**
     * In user _create, pre-set Group depending on type of
     * the created user. e.g. Coachs, Providers or Individuals.
     *
     * @return object
     */
    public function getNewInstance()
    {
        $coach = parent::getNewInstance();
        $group = $this->getUserGroup();

        $coach->setGroup($group);
        $coach->setEnabled(true);

        return $coach;
    }

    /**
     * Pre-filter lists depending on Group.
     * e.g. In Provider List get only users with group = Providers.
     */
    public function getFilterParameters()
    {
        $filterByGroup = ['group' => ['value' => $this->getUserGroup() ? $this->getUserGroup()->getId() : '']];
        $this->datagridValues = array_merge($filterByGroup, $this->datagridValues);

        return parent::getFilterParameters();
    }
}
