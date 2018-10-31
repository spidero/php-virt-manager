<?php

require_once ('function.php');

if (isset($_GET['node']) AND isset($_GET['state'])){
	$node	= filter_input(INPUT_GET, 'node',  FILTER_SANITIZE_STRING);
	$state	= filter_input(INPUT_GET, 'state',  FILTER_SANITIZE_STRING);
//	echo "$node|$state";
	
	if ($state=="start"){
        $res  = libvirt_domain_lookup_by_name ($con, $node);
        $info = libvirt_domain_create($res);
        if ($info==1){
            $box_info = 
            '
                    <div class="card text-white bg-success mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Starting machine, it may take some time</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
        else {
                    $box_info = 
            '
                    <div class="card text-white bg-danger mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Error: '.$info.'</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
	}
	elseif ($state=="stop"){
	    $res  = libvirt_domain_lookup_by_name ($con, $node);
        $info = libvirt_domain_shutdown($res);
        
        if ($info==1){
            $box_info = 
            '
                    <div class="card text-white bg-success mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Shuting down machine, it may take some time</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
        else {
                    $box_info = 
            '
                    <div class="card text-white bg-danger mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Error: '.$info.'</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
	}
	elseif ($state=="reboot"){
	    $res  = libvirt_domain_lookup_by_name ($con, $node);
        $info = libvirt_domain_reboot($res);
        if ($info==1){
            $box_info = 
            '
                    <div class="card text-white bg-success mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Rebooting machine, it may take some time</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
        else {
                    $box_info = 
            '
                    <div class="card text-white bg-danger mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Error: '.$info.'</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
	}
	
	elseif ($state=="suspend"){
	    $res  = libvirt_domain_lookup_by_name ($con, $node);
        $info = libvirt_domain_suspend($res);
        if ($info==1){
            $box_info = 
            '
                    <div class="card text-white bg-success mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Suspening machine, it may take some time</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
        else {
                    $box_info = 
            '
                    <div class="card text-white bg-danger mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Error: '.$info.'</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
	}	
	elseif ($state=="resume"){
	    $res  = libvirt_domain_lookup_by_name ($con, $node);
        $info = libvirt_domain_resume($res);
        if ($info==1){
            $box_info = 
            '
                    <div class="card text-white bg-success mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Resuming machine, it may take some time</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
        else {
                    $box_info = 
            '
                    <div class="card text-white bg-danger mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Error: '.$info.'</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
	}	
	
		elseif ($state=="destroy"){
		$res  = libvirt_domain_lookup_by_name ($con, $node);
        $info = libvirt_domain_destroy($res);
        if ($info==1){
            $box_info = 
            '
                    <div class="card text-white bg-success mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Destroying machine, it may take some time</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
        else {
                    $box_info = 
            '
                    <div class="card text-white bg-danger mb-3" style="max-width: 100%;">
                        <div class="card-header">Info</div>
                            <div class="card-body">
                                <h5 class="card-title">Error: '.$info.'</h5>
                        </div>
                    </div>
            ';
            $smarty->assign('box_info', $box_info);
        }
	}
	
}

if (isset($_GET['node'])){
	$node	= filter_input(INPUT_GET, 'node',  FILTER_SANITIZE_STRING);
	
    $smarty->assign('node', $node);
    //print_r( libvirt_domain_get_info (libvirt_domain_lookup_by_name ($con, $node)) );
    //echo "<hr>";
    
    $res = libvirt_domain_lookup_by_name ($con, $node);
    
    $domain_uuid = libvirt_domain_get_uuid_string ($res);
    $smarty->assign('domain_uuid', $domain_uuid);

    $active = libvirt_domain_is_active($res);
    $smarty->assign('active', $active);

    $info = libvirt_domain_get_info($res);
    $info['maxMem'] = round($info['maxMem']/(1024*1024)); 
    $info['memory'] = round($info['memory']/(1024*1024)); 
    $smarty->assign('info', $info);
    
    if ($info['state']==1){
        $vnc_ip = xpath_return($res, '//domain/devices/graphics/@listen');
        $smarty->assign('vnc_ip', $vnc_ip);
        
        $vnc_port = xpath_return($res, '//domain/devices/graphics/@port');
        $smarty->assign('vnc_port', $vnc_port);
        
        //print_r(xpath_return($res, '//domain/devices/disk[@device="disk"]/target/@bus', false));
        //print_r( libvirt_domain_xml_xpath($res, '//domain/devices/disk[@device="disk"]/target/@bus', false));
        
        //echo "<br>";
        //print_r(xpath_return($res, '//domain/devices/disk[@device="disk"]/target/@dev', false));
        //print_r( libvirt_domain_xml_xpath($res, '//domain/devices/disk[@device="disk"]/target/@dev', false));
        
        //echo "<br>[";
        //print_r(libvirt_domain_get_block_info('$res', 'hda'));
        //print_r(libvirt_domain_get_autostart('$res'));
        //echo "]<br>";
        
    }

}
else {
    header ("Location: index.php");
}

$smarty_name =  basename(__FILE__, '.php').'.tpl';
$smarty->display($smarty_name);

?>
