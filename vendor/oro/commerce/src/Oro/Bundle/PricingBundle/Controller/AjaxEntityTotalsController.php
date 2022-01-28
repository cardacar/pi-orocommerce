<?php

namespace Oro\Bundle\PricingBundle\Controller;

use Oro\Bundle\EntityBundle\Exception\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AjaxEntityTotalsController extends AbstractController
{
    /**
     * @Route(
     *      "/get-totals-for-entity/{entityClassName}/{entityId}",
     *      name="oro_pricing_entity_totals",
     *      requirements={"entityId"="\d+"},
     *      defaults={"entityId"=0, "entityClassName"=""}
     * )
     *
     * @param string $entityClassName
     * @param integer $entityId
     *
     * @return JsonResponse
     */
    public function getEntityTotalsAction($entityClassName, $entityId)
    {
        try {
            $totalRequestHandler = $this->get('oro_pricing.subtotal_processor.handler.request_handler');
            $totals = $totalRequestHandler->recalculateTotals($entityClassName, $entityId);
        } catch (EntityNotFoundException $e) {
            return new JsonResponse('', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            $totals
        );
    }

    /**
     * @Route(
     *      "/recalculate-totals-for-entity/{entityClassName}/{entityId}",
     *      name="oro_pricing_recalculate_entity_totals",
     *      defaults={"entityId"=0, "entityClassName"=""},
     *      methods={"POST"}
     * )
     *
     * @param Request $request
     * @param string $entityClassName
     * @param integer $entityId
     *
     * @return JsonResponse
     */
    public function recalculateTotalsAction(Request $request, $entityClassName, $entityId)
    {
        try {
            $totalRequestHandler = $this->get('oro_pricing.subtotal_processor.handler.request_handler');
            $totals = $totalRequestHandler->recalculateTotals($entityClassName, $entityId, $request);
        } catch (EntityNotFoundException $e) {
            return new JsonResponse('', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            $totals
        );
    }
}
