<?php
/**
 * NDP_Monetico extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License ("OSL") v. 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @category       NDP
 * @package        NDP_Monetico
 * @copyright      Copyright (c) 2017
 * @author         NDP Systèmes
 * @license        Open Software License ("OSL") v. 3.0
 */

namespace NDP\Monetico\Model\Config\Source;

/**
 * @api
 * @since 100.0.2
 */
class ThreeDsc implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'no_preference', 'label' => __("Pas de préférence")],
            ['value' => 'challenge_preferred', 'label' => __("Authentification souhaitée")],
            ['value' => 'challenge_mandated', 'label' => __("Authentification systématique demandée")],
            ['value' => 'no_challenge_requested', 'label' => __("Pas d’authentification demandée")],
            ['value' => 'no_challenge_requested_strong_authentication', 'label' => __("Pas d’authentification demandée type d'exemption l'authentification forte")],
            ['value' => 'no_challenge_requested_trusted_third_party', 'label' => __("Pas d’authentification demandée type d'exemption tiers de confiance")],
            ['value' => 'no_challenge_requested_risk_analysis', 'label' => __("Pas d’authentification demandée type d'exemption analyse de risque préalable faite")]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'no_preference' => __("Pas de préférence"),
            'challenge_preferred' => __("Authentification souhaitée"),
            'challenge_mandated' => __("Authentification systématique demandée"),
            'no_challenge_requested' => __("Pas d’authentification demandée"),
            'no_challenge_requested_strong_authentication' => __("Pas d’authentification demandée type d'exemption l'authentification forte"),
            'no_challenge_requested_trusted_third_party' => __("Pas d’authentification demandée type d'exemption tiers de confiance"),
            'no_challenge_requested_risk_analysis' => __("Pas d’authentification demandée type d'exemption analyse de risque préalable faite")
        ];
    }
}
