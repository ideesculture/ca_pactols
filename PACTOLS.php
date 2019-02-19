<?php
/** ---------------------------------------------------------------------
 * app/lib/core/Plugins/InformationService/Pactols.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2015 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This source code is free and modifiable under the terms of
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * @package CollectiveAccess
 * @subpackage InformationService
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */

/**
 * This file : IDEESCULTURE 2019
 * Created by Gautier MICHELIN
 */


require_once(__CA_LIB_DIR__ . "/core/Plugins/IWLPlugInformationService.php");
require_once(__CA_LIB_DIR__ . "/core/Plugins/InformationService/BaseInformationServicePlugin.php");

global $g_information_service_settings_pactols;
$g_information_service_settings_pactols = array();

//specific requester pactols returns a 202 !!! code
function PactolsQuery($ps_url) {
	if(!isURL($ps_url)) { return false; }
	$o_conf = Configuration::load();

	$vo_curl = curl_init();
	curl_setopt($vo_curl, CURLOPT_URL, $ps_url);

	if($vs_proxy = $o_conf->get('web_services_proxy_url')){ /* proxy server is configured */
		curl_setopt($vo_curl, CURLOPT_PROXY, $vs_proxy);
	}

	curl_setopt($vo_curl, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($vo_curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($vo_curl, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($vo_curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($vo_curl, CURLOPT_AUTOREFERER, true);
	curl_setopt($vo_curl, CURLOPT_CONNECTTIMEOUT, 120);
	curl_setopt($vo_curl, CURLOPT_TIMEOUT, 120);
	curl_setopt($vo_curl, CURLOPT_MAXREDIRS, 10);
	curl_setopt($vo_curl, CURLOPT_USERAGENT, 'CollectiveAccess web service lookup');
	$headers = array(
        'Accept: application/json'
    );
    curl_setopt($vo_curl, CURLOPT_HTTPHEADER, $headers);
	$vs_content = curl_exec($vo_curl);

	if(!in_array(curl_getinfo($vo_curl, CURLINFO_HTTP_CODE), [200, 202])) {
		throw new \Exception(_t('An error occurred while querying an external webservice'));
	}

	curl_close($vo_curl);
	return $vs_content;
}

class WLPlugInformationServicePactols Extends BaseInformationServicePlugin Implements IWLPlugInformationService {
    # ------------------------------------------------
    static $s_settings;
    # ------------------------------------------------
    /**
     *
     */
    public function __construct() {
        global $g_information_service_settings_pactols;

        WLPlugInformationServicePactols::$s_settings = $g_information_service_settings_pactols;
        parent::__construct();
        $this->info['NAME'] = 'Pactols';

        $this->description = _t('Provides access to Pactols thesaurus pactols.frantiq.fr');
    }
    # ------------------------------------------------
    /**
     * Get all settings settings defined by this plugin as an array
     *
     * @return array
     */
    public function getAvailableSettings() {
        return WLPlugInformationServicePactols::$s_settings;
    }
    # ------------------------------------------------
    # Data
    # ------------------------------------------------
    /**
     * Perform lookup on Pactols-based data service
     *
     * @param array $pa_settings Plugin settings values
     * @param string $ps_search The expression with which to query the remote data service
     * @param array $pa_options Lookup options (none defined yet)
     * @return array
     */
    public function lookup($pa_settings, $ps_search, $pa_options=null) {
        // support passing full Pactols URLs
        //if(isURL($ps_search)) { $ps_search = self::getPageTitleFromURI($ps_search); }
        $vs_url = caGetOption('url', $pa_settings, 'https://pactols.frantiq.fr/opentheso/api');
        // readable version of get parameters
        // We have a string, let's search it
        if(strpos($ps_search,"ark:") === false) {
            $vs_content = PactolsQuery(
                $vs_url ."/autocomplete/". urlencode($ps_search)."?lang=fr&theso=TH_1"
            );

            $va_content = @json_decode($vs_content, true);

            // the top two levels are 'result' and 'resume'
            $va_return = array();
            foreach($va_content as $va_result) {
                // Skip non person names
                $va_return['results'][] = array(
                    'label' => $va_result["label"],
                    'url' => $va_result["uri"],
                    'idno' => str_ireplace("https://ark.frantiq.fr/", "", $va_result["uri"]),
                );
            }

        } else {
            // Otherwise it's a Pactols ID
            $vs_content = PactolsQuery(
                $vs_url_search = $vs_url. str_replace("ark:","",$ps_search).".json"
            );

            $va_content = @json_decode($vs_content, true);
            $va_content=reset($va_content);
            //extracting label
            foreach($va_content["http://www.w3.org/2004/02/skos/core#prefLabel"] as $label) {
	            if($label["lang"] == "en") {
		            $en_label = $label["value"];
	            }
	            if($label["lang"] == "fr") {
		            $fr_label = $label["value"];
	            }
            }
            $label = ($fr_label ? $fr_label : $en_label);

            $va_return['results'][] = array(
                'label' => $label,
                'url' => "https://ark.frantiq.fr/".$ps_search,
                'idno' => $ps_search
            );
            
        }
        return $va_return;
    }

    # ------------------------------------------------
    /**
     * Fetch details about a specific item from a Iconclass-based data service for "more info" panel
     *
     * @param array $pa_settings Plugin settings values
     * @param string $ps_url The URL originally returned by the data service uniquely identifying the item
     * @return array An array of data from the data server defining the item.
     */
    public function getExtendedInformation($pa_settings, $ps_url) {
        $vs_url = str_replace("https://ark.frantiq.fr/ark:","https://pactols.frantiq.fr/opentheso/api",$ps_url);
        //https://ark.frantiq.fr/ark:/26678/pcrtVJZca9L0GP
        //https://pactols.frantiq.fr/opentheso/api/26678/pcrtkOgxvd4Ijy
        $vs_content = PactolsQuery($vs_url);
        $va_content = @json_decode($vs_content, true);
        $va_content=reset($va_content);
            //extracting label
        foreach($va_content["http://www.w3.org/2004/02/skos/core#prefLabel"] as $label) {
            if($label["lang"] == "en") {
	            $en_label = $label["value"];
            }
            if($label["lang"] == "fr") {
	            $fr_label = $label["value"];
            }
        }
        $label = ($fr_label ? $fr_label : $en_label);
		$vs_parent_url = str_replace("https://ark.frantiq.fr/ark:","https://pactols.frantiq.fr/opentheso/api",reset($va_content["http://www.w3.org/2004/02/skos/core#broader"])["value"]);
		$vs_parent_content = PactolsQuery($vs_parent_url);
        $va_parent_content = @json_decode($vs_parent_content, true);
        $va_parent_content = reset($va_parent_content);
	        foreach($va_parent_content["http://www.w3.org/2004/02/skos/core#prefLabel"] as $parent_label) {

            if($parent_label["lang"] == "en") {
	            $en_parent_label = $parent_label["value"];
            }
            if($parent_label["lang"] == "fr") {
	            $fr_parent_label = $parent_label["value"];
            }
        }
        $parent_label = ($fr_parent_label ? $fr_parent_label : $en_parent_label);
         
        $definition = "";  
        if(isset($va_content["http://www.w3.org/2004/02/skos/core#definition"]) && isset(reset($va_content["http://www.w3.org/2004/02/skos/core#definition"])["value"])) {
	        $definition = reset($va_content["http://www.w3.org/2004/02/skos/core#definition"])["value"];
        }
        $vs_display .= "<h3>(...) > ".$parent_label." > ".$label."</h3>";
        $vs_display .= "<p><a href='$ps_url' target='_blank'>voir sur Frantiq</a></p>";
        $vs_display .= "<p>Terme parent : ".reset($va_content["http://www.w3.org/2004/02/skos/core#broader"])["value"]."</p>";
        $vs_display .= "<p>".$definition."</p>";

        return array('display' => $vs_display);
    }
    # ------------------------------------------------
}