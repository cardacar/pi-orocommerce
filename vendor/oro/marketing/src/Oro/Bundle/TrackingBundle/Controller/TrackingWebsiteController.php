<?php

namespace Oro\Bundle\TrackingBundle\Controller;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\TrackingBundle\Entity\TrackingWebsite;
use Oro\Bundle\TrackingBundle\Form\Type\TrackingWebsiteType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * CRUD for tracking websites.
 *
 * @Route("/tracking/website")
 */
class TrackingWebsiteController extends AbstractController
{
    /**
     * @Route(
     *      ".{_format}",
     *      name="oro_tracking_website_index",
     *      requirements={"_format"="html|json"},
     *      defaults={"_format" = "html"}
     * )
     * @Acl(
     *      id="oro_tracking_website_view",
     *      type="entity",
     *      class="OroTrackingBundle:TrackingWebsite",
     *      permission="VIEW"
     * )
     * @Template
     */
    public function indexAction()
    {
        return [
            'entity_class' => TrackingWebsite::class
        ];
    }

    /**
     * @Route("/create", name="oro_tracking_website_create")
     * @Acl(
     *      id="oro_tracking_website_create",
     *      type="entity",
     *      class="OroTrackingBundle:TrackingWebsite",
     *      permission="CREATE"
     * )
     * @Template("OroTrackingBundle:TrackingWebsite:update.html.twig")
     */
    public function createAction()
    {
        return $this->update(new TrackingWebsite());
    }

    /**
     * @Route("/update/{id}", name="oro_tracking_website_update", requirements={"id"="\d+"})
     * @Acl(
     *      id="oro_tracking_website_update",
     *      type="entity",
     *      class="OroTrackingBundle:TrackingWebsite",
     *      permission="EDIT"
     * )
     * @Template()
     * @param TrackingWebsite $trackingWebsite
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updateAction(TrackingWebsite $trackingWebsite)
    {
        return $this->update($trackingWebsite);
    }

    /**
     * @Route("/view/{id}", name="oro_tracking_website_view", requirements={"id"="\d+"})
     * @AclAncestor("oro_tracking_website_view")
     * @Template()
     * @param TrackingWebsite $trackingWebsite
     * @return array
     */
    public function viewAction(TrackingWebsite $trackingWebsite)
    {
        return [
            'entity' => $trackingWebsite
        ];
    }

    /**
     * @param TrackingWebsite $trackingWebsite
     * @return array|RedirectResponse
     */
    protected function update(TrackingWebsite $trackingWebsite)
    {
        return $this->get('oro_form.model.update_handler')->update(
            $trackingWebsite,
            $this->createForm(TrackingWebsiteType::class),
            $this->getTranslator()->trans('oro.tracking.trackingwebsite.saved_message')
        );
    }

    /**
     * @return TranslatorInterface
     */
    protected function getTranslator()
    {
        return $this->get('translator');
    }
}
