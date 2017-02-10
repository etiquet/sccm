<?php
/*
 *
 -------------------------------------------------------------------------
 GLPISCCMPlugin
 Copyright (C) 2013 by teclib.

 http://www.teclib.com
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPISCCMPlugin.

 GLPISCCMPlugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPISCCMPlugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPISCCMPlugin. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
*/

// Original Author of file: François Legastelois <flegastelois@teclib.com>
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginSccmSccmxml {

   var $data;
   var $device_id;
   var $sxml;
   var $agentbuildnumber;
   var $username;

   function PluginSccmSccmxml($data) {

      $plug = new Plugin();
      $plug->getFromDBbyDir("sccm");

      $this->data = $data;
      $this->device_id = $data['MD-SystemName']."_".$data['CSD-MachineID'];
      $this->agentbuildnumber = "SCCM-v".$plug->fields['version'];

$SXML=<<<XML
<?xml version='1.0' encoding='UTF-8'?>
<REQUEST>
   <CONTENT>
      <VERSIONCLIENT>{$this->agentbuildnumber}</VERSIONCLIENT>
   </CONTENT>
   <DEVICEID>{$this->device_id}</DEVICEID>
   <TOKEN>SOC_{$this->device_id}</TOKEN>
   <QUERY>INVENTORY</QUERY>
   <PROLOG></PROLOG>
</REQUEST>
XML;
      $this->sxml = new SimpleXMLElement($SXML);
   }

   function setAccessLog() {
      $CONTENT = $this->sxml->CONTENT[0];
      $CONTENT->addChild('ACCESSLOG');

      $ACCESSLOG = $this->sxml->CONTENT[0]->ACCESSLOG;
      $ACCESSLOG->addChild('LOGDATE',date('Y-m-d h:i:s'));

      if(!empty($this->data['VrS-UserName'])) {
         $this->username = $this->data['VrS-UserName'];
      } else {
         if(!empty($this->data['SDI-UserName'])) {
            $this->username = $this->data['SDI-UserName'];
         } else{
            if(!empty($this->data['CSD-UserName'])) {
               if(preg_match_all("#\\ (.*)#",$this->data['CSD-UserName'],$matches)) {
                  $this->data['CSD-UserName'] = $matches[1][0];
               }

               $this->username = $this->data['CSD-UserName'];
            } else {
               $this->username = "";
            }

         }  
      }


      $ACCESSLOG->addChild('USERID',$this->username);
   }
   
   function setAccountInfos() {
      $CONTENT = $this->sxml->CONTENT[0];
      $CONTENT->addChild('ACCOUNTINFO');

      $ACCOUNTINFO = $this->sxml->CONTENT[0]->ACCOUNTINFO;
      $ACCOUNTINFO->addChild('KEYNAME','TAG');
      $ACCOUNTINFO->addChild('KEYVALUE','SCCM');
   }

   function setHardware() {
      $CONTENT = $this->sxml->CONTENT[0];
      $CONTENT->addChild('HARDWARE');
      
      $HARDWARE = $this->sxml->CONTENT[0]->HARDWARE;
      $HARDWARE->addChild('NAME',strtoupper($this->data['MD-SystemName']));
      $HARDWARE->addChild('CHASSIS_TYPE',$this->data['SD-SystemRole']);
      $HARDWARE->addChild('LASTLOGGEDUSER',$this->username);
      $HARDWARE->addChild('UUID',substr($this->data['SD-UUID'],5));
   }

   function setOS() {
      $versionOS = $this->data['OSD-CSDVersion'] 
                  . "." . $this->data['OSD-Version'] 
                  . "." . $this->data['OSD-BuildNumber'];

      $HARDWARE = $this->sxml->CONTENT[0]->HARDWARE;
      $HARDWARE->addChild('OSNAME'     ,$this->data['OSD-Caption']);
      $HARDWARE->addChild('OSCOMMENTS' ,$this->data['OSD-CSDVersion']);
      $HARDWARE->addChild('OSVERSION'     ,$versionOS);
   }

   function setBios() {
      $CONTENT = $this->sxml->CONTENT[0];
      $CONTENT->addChild('BIOS');
      
      $BIOS = $this->sxml->CONTENT[0]->BIOS;
      $BIOS->addChild('ASSETTAG'       ,$this->data['PBD-SerialNumber']);
      $BIOS->addChild('SMODEL'         ,$this->data['CSD-Model']);
      $BIOS->addChild('TYPE'           ,$this->data['SD-SystemRole']);
      $BIOS->addChild('MMANUFACTURER'     ,$this->data['CSD-Manufacturer']);
      $BIOS->addChild('SMANUFACTURER'     ,$this->data['CSD-Manufacturer']);
      $BIOS->addChild('SSN'            ,$this->data['PBD-SerialNumber']);

      // Jul 17 2012 12:00:00:000AM
      $Date_Sccm = DateTime::createFromFormat('M d Y', 
            substr($this->data['PBD-ReleaseDate'],0,12));

      if($Date_Sccm != false) {
         $this->data['PBD-ReleaseDate'] = $Date_Sccm->format('m/d/Y');
      }

      $BIOS->addChild('BDATE'          ,$this->data['PBD-ReleaseDate']);
      $BIOS->addChild('BMANUFACTURER'     ,$this->data['PBD-Manufacturer']);
      $BIOS->addChild('BVERSION'       ,$this->data['PBD-BiosVersion']);
      $BIOS->addChild('SKUNUMBER'         ,$this->data['PBD-Version']);
   }

   function setProcessors() {

      $PluginSccmSccm = new PluginSccmSccm();

      $cpukeys = array();

      $CONTENT    = $this->sxml->CONTENT[0]; $i = 0;
      foreach($PluginSccmSccm->getDatas('processors', $this->device_id) as $value){
         if(!in_array($value['CPUKey00'], $cpukeys)) {
            $CONTENT->addChild('CPUS');
            $CPUS = $this->sxml->CONTENT[0]->CPUS[$i];
            $CPUS->addChild('DESCRIPTION'    ,$value['Name00']);
            $CPUS->addChild('MANUFACTURER'      ,$value['Manufacturer00']);
            $CPUS->addChild('NAME'           ,$value['Name00']);
            $CPUS->addChild('SPEED'          ,$value['NormSpeed00']);
            $CPUS->addChild('TYPE'           ,$value['AddressWidth00']);
            $i++; 

            // save actual cpukeys for duplicity
            $cpukeys[] = $value['CPUKey00'];
         }
      }
   }

   function setSoftwares() {
      
      $PluginSccmSccm = new PluginSccmSccm();

      $antivirus = array(); $inject_antivirus = false;
      $CONTENT    = $this->sxml->CONTENT[0]; $i = 0;
      foreach($PluginSccmSccm->getSoftware($this->device_id) as $value){

         $CONTENT->addChild('SOFTWARES');
         $SOFTWARES = $this->sxml->CONTENT[0]->SOFTWARES[$i];

         if (preg_match("#&#", $value['ArPd-DisplayName']) ) {
            $value['ArPd-DisplayName'] = preg_replace("#&#","&amp;", $value['ArPd-DisplayName']);
         }

         if (preg_match("#&#", $value['ArPd-Publisher']) ) {
            $value['ArPd-Publisher'] = preg_replace("#&#","&amp;", $value['ArPd-Publisher']);
         }

         $SOFTWARES->addChild('NAME' ,$value['ArPd-DisplayName']);

         if(isset($value['ArPd-Version'])) {
            $SOFTWARES->addChild('VERSION' ,$value['ArPd-Version']);
         }

         if(isset($value['ArPd-Publisher'])) {
            $SOFTWARES->addChild('PUBLISHER' ,$value['ArPd-Publisher']);
         }

         $i++;

         if(preg_match('#Kaspersky Endpoint Security#',$value['ArPd-DisplayName'])){
            $antivirus = $value['ArPd-DisplayName'];
            $inject_antivirus = true;
         }
      }
      
      if($inject_antivirus) {
         $this->setAntivirus($antivirus);
      }
   }

   function setAntivirus($value) {
      $CONTENT    = $this->sxml->CONTENT[0];
      $CONTENT->addChild('ANTIVIRUS');
      
      $ANTIVIRUS = $this->sxml->CONTENT[0]->ANTIVIRUS;
      $ANTIVIRUS->addChild('NAME',$value);
   }

   function setUsers() {
      $CONTENT = $this->sxml->CONTENT[0];
      $CONTENT->addChild('USERS');

      $USERS = $this->sxml->CONTENT[0]->USERS;
      $USERS->addChild('DOMAIN'  ,$this->data['CSD-Domain']);
      $USERS->addChild('LOGIN'   ,$this->username);
   }

   function setNetworks() {
      
      $PluginSccmSccm = new PluginSccmSccm();

      $CONTENT = $this->sxml->CONTENT[0];

      $networks = $PluginSccmSccm->getNetwork($this->device_id);

      if(count($networks) > 0) {

         $i = 0;

         foreach($networks as $value){

            $CONTENT->addChild('NETWORKS');
            $NETWORKS = $this->sxml->CONTENT[0]->NETWORKS[$i];

            $NETWORKS->addChild('IPADDRESS'     ,$value['ND-IpAddress']);
            $NETWORKS->addChild('DESCRIPTION'   ,$value['ND-Name']);
            $NETWORKS->addChild('IPMASK'     ,$value['ND-IpSubnet']);
            $NETWORKS->addChild('IPDHCP'     ,$value['ND-DHCPServer']);
            $NETWORKS->addChild('IPGATEWAY'     ,$value['ND-IpGateway']);
            $NETWORKS->addChild('MACADDR'       ,$value['ND-MacAddress']);
            $NETWORKS->addChild('DOMAIN'        ,$value['ND-DomainName']);

            $i++;
         }
      }
   }

   function setDrives() {
      $PluginSccmSccm = new PluginSccmSccm();

      $CONTENT    = $this->sxml->CONTENT[0]; $i = 0;
      foreach($PluginSccmSccm->getDatas('drives', $this->device_id) as $value){
         $CONTENT->addChild('DRIVES');
         $DRIVES = $this->sxml->CONTENT[0]->DRIVES[$i];
         $DRIVES->addChild('DESCRIPTION'     ,$value['Description00']);
         //$DRIVES->addChild('FILESYSTEM'    ,$value['attr_14807']);
         //$DRIVES->addChild('FREE'       ,$value['attr_14805']);
         $DRIVES->addChild('LABEL'        ,$value['Caption00']);
         //$DRIVES->addChild('LETTER'        ,$value['name']);
         $DRIVES->addChild('TYPE'         ,$value['InterfaceType00']);
         $DRIVES->addChild('TOTAL'        ,$value['Size00']);
         $i++;
      }
   }

   function setMonitors() {
      $PluginSccmSccm = new PluginSccmSccm();

      $CONTENT    = $this->sxml->CONTENT[0]; $i = 0;

    // note fusion inventory ne remonte que ces 3 valeurs
      foreach($PluginSccmSccm->getMonitors($this->device_id) as $value){
         $CONTENT->addChild('MONITORS');
         $MONITORS = $this->sxml->CONTENT[0]->MONITORS[$i];
         $MONITORS->addChild('MANUFACTURER'     ,$value['mddata-Manufacturer']);
         $MONITORS->addChild('CAPTION'       ,$value['mddata.Name']." (".$value['mddata-DiagonalSize']."\")" );
         $MONITORS->addChild('SERIAL'        ,$value['mddata-SerialNumber']);

         $i++;
    }

   }

   function object2array($object) { 
      return @json_decode(@json_encode($object),1); 
   }

}

?>
