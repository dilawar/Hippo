<?php

function addNewTransportationSchedule(array $data): array
{
    $ret = ['msg'=>'', 'success'=>false];

    // Add a new schedule.
    $keys = 'pickup_point,drop_point,day,trip_start_time,duration,vehicle';
    foreach(explode(',', $keys) as $key) 
    {
        if(! $data[$key])
        {
            $ret['msg'] .= "Missing key $key";
            $ret['status'] = false;
            return $ret;
        }
    }

    if(intval($data['duration']) <= 5)
    {
        $ret['success'] = false;
        $ret['msg'] .= "Duration is too small.";
        return $ret;
    }
    $data['trip_end_time'] = dbTime(strtotime($data['trip_start_time'])
        + intval($data['duration'])*60);
    $data['id'] = getUniqueID('transport');
    $data['last_modified_on'] = dbDateTime('now');
    $data['edited_by'] = getLogin();
    insertIntoTable('transport', "id,$keys,created_by,comment,last_modified_on", $data);
    $ret['success'] = true;
    return $ret;
}

function getVehiclesInAvailableTransport() : array
{
    $res = executeQuery("SELECT DISTINCT vehicle FROM transport WHERE status='VALID'");
    $available = array_map(function ($x) { return $x['vehicle'];}, $res);
    return $available;
}

function getRoutesInAvailableTransport()
{
    $entries = executeQuery("SELECT DISTINCT pickup_point,drop_point,url FROM transport WHERE status='VALID'");
    $data = [];
    foreach($entries as $ent)
        $data[$ent['pickup_point'].$ent['drop_point']] = $ent;
    return $data;
}

function getRoutes() : array
{
    $routes = getRoutesInAvailableTransport();
    $confRoutes = json_decode(getConfigValue('transport.route'), true);
    if(is_array($confRoutes))
        $routes = array_merge($routes, $confRoutes);

    insertOrUpdateTable('config', 'id,value', 'value',
        ['id'=>'transport.route', 'value'=>json_encode($routes)]);
    return $routes;
}

function addRoute($route): array
{
    $ret = ['success'=>false, 'msg'=> ''];
    $routes = getRoutes();

    $key = $route['pickup_point'].$route['drop_point'];
    if(in_array($key, $routes)) {
        $ret['msg'] = "Already exists";
        return $ret;
    }

    // Add.
    $routes[$key] = $route;
    try {
        $ret['success'] = updateTable('config', 'id', 'value'
            , ['id'=>'transport.route' , 'value'=>json_encode($routes)]
        );
    } catch (Exception $e) {
        $ret['msg'] = $e->getMessage();
    }
    return $ret;
}

function deleteRoute($route) : array 
{
    $ret = ['success'=>false, 'msg'=> ''];
    $routes = getRoutes();
    $key = $route['pickup_point'].$route['drop_point'];
    unset($routes[$key]);
    try {
        $ret['success'] = updateTable('config', 'id', 'value'
            , ['id'=>'transport.route' , 'value'=>json_encode($routes)]
        );
    } catch (Exception $e) {
        $ret['msg'] = $e->getMessage();
    }
    return $ret;
}

?>
