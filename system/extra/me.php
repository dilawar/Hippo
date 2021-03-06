<?php 

/**
    * @brief Add a new talk.
    *
    * @param $data
    *
    * @return Id of talk or empty array.
 */
function addNewTalk( array $data ) : array
{
    $hippoDB = initDB();
    // Get the max id
    $id = getUniqueID('talks');
    $data[ 'id' ] = $id;
    try {
        $res = insertIntoTable( 'talks'
            , 'id,host,class,coordinator,title,speaker,speaker_id,description,created_by,created_on'
            , $data );
        return array("id" => $id);
    } catch (Exception $e) {
        return ['success'=>false, 'msg'=>'Failed to create new talk' . $e->getMessage()];
    }
    return ['success'=>false, 'msg'=>'Failed to create new talk'];
}

/* --------------------------------------------------------------------------*/
/**
    * @Synopsis  Get my talks which are not scheduled yet.
    *
    * @Param $created_by
    * @Param $unbooked
    *
    * @Returns   
 */
/* ----------------------------------------------------------------------------*/
function getMyUnscheduledTalks($created_by)
{
    $talks = getTableEntries( 'talks', 'created_on'
        , "status='VALID' AND created_by='$created_by'");

    $results = [];
    foreach($talks as &$talk)
    {
        if(! __get__($talk,'class', ''))
            continue;

        $request = getBookingRequestOfTalkId($talk['id']);
        $ev = getEventsOfTalkId($talk['id']);
        if($request)
            continue;
        if($ev)
            continue;
        $results[] = $talk;
    }
    return $results;
}

/* --------------------------------------------------------------------------*/
/**
    * @Synopsis  Rreturn all my talks.
    *
    * @Param $login
    *
    * @Returns   
 */
/* ----------------------------------------------------------------------------*/
function getMyTalks(string $created_by, bool $withBooking=true
    , bool $onlyUnscheduledAndUpcoming=false): array
{
    $talks = getTableEntries( 'talks', 'created_on'
        , "status='VALID' AND speaker_id > '0' AND created_by='$created_by'");

    if(! $withBooking)
        return $talks;

    $results = [];
    foreach($talks as &$talk)
    {
        if(! __get__($talk,'class', ''))
            continue;

        $talk['booking'] = [];
        $talk['booking_status'] = 'UNSCHEDULED';
        $request = getBookingRequestOfTalkId($talk['id']);
        if($request)
        {
            $talk['booking'] = $request;
            $talk['booking_status'] = 'PENDING';
            if($onlyUnscheduledAndUpcoming)
                continue;
        }

        // Ignore any talk which is deliverd more than 1 days ago.
        $ev = getEventsOfTalkId($talk['id']);
        if($ev)
        {
            if(strtotime($ev['date']) < strtotime('yesterday'))
                continue;
            $talk['booking'] = $ev;
            $talk['booking_status'] = 'CONFIRMED';
            if($onlyUnscheduledAndUpcoming)
                continue;
        }
        $results[] = $talk;
    }
    return $results;
}

/* --------------------------------------------------------------------------*/
/**
    * @Synopsis  Cancel this talk.
    *
    * @Param $talkid
    *
    * @Returns   
 */
/* ----------------------------------------------------------------------------*/
function cancelTalk( $talkid )
{
    $res = updateTable( 'talks', 'id', 'status'
        , ['id'=>$talkid, 'status'=>'CANCELLED']
    ); 
    return $res;
}

function getVenuesWithStatusOnThisDayAndTime($date, $startTime, $endTime): array
{
    // Fetch all venues allowed for booking.
    $venues = getVenues('id');
    foreach($venues as &$venue)
    {
        $venue['BOOKING_STATUS'] = 'AVAILABLE';
        $venue['BOOKING'] = [];
        $ev = getEventsOnThisVenueBetweenTime($venue['id'], $date, $startTime, $endTime);
        if($ev && count($ev) > 0)
        {
            $venue['BOOKING_STATUS'] = 'BOOKED';
            $venue['BOOKING'] = @eventToText($ev[0]);
        }
        else
        {
            $req = getRequestsOnThisVenueBetweenTime($venue['id'], $date, $startTime, $endTime);
            if($req && count($req) > 0)
            {
                $venue['BOOKING_STATUS'] = 'REQUEST PENDING';
                $venue['BOOKING'] = @eventToText($req[0]);
            }
        }
    }
    return $venues;
}

/* --------------------------------------------------------------------------*/
/**
    * @Synopsis  User photo if available.
    *
    * @Param string
    *
    * @Returns   
 */
/* ----------------------------------------------------------------------------*/
function getUserPhotoB64(string $user): string 
{
    $conf = getConf( );
    $picPath = $conf['data']['user_imagedir'] . '/' . $user . '.jpg';
    if(file_exists($picPath))
        return base64_encode(file_get_contents($picPath));
    return '';
}

function getProfileEditables($admin=false): array
{ 
    $schema = getTableSchema('logins');
    $fs = 'title:select,first_name:text,honorific:select,last_name:text,'
        .  'alternative_email:email,institute:text' 
        .  ',joined_on:date,pi_or_host:select,specialization:select';

    if($admin)
        $fs .= ",roles:select,status:select";

    $res = [];

    // Prepare select list of faculty.
    $faculty = getTableEntries( 'faculty', 'email', "status='ACTIVE'" );
    $facultyEmails = array();
    foreach( $faculty as $fac )
        $facultyEmails[] = $fac['email'];

    $specializations = array_map(
        function( $x ) { return $x['specialization']; }, getAllSpecialization( )
    );


    foreach(explode(',', $fs) as $f)
    {
        $d = explode(':', $f);
        $options = '';
        if($d[1] === 'select')
            $options = getTableColumnTypes('logins', $d[0]);
        $res[$d[0]] =[$d[1], $options];
    }
    $res['specialization'][1] = $specializations;
    $res['pi_or_host'][1] = $facultyEmails;
    return $res;
}

/* --------------------------------------------------------------------------*/
/**
    * @Synopsis  Submit feedback for a single question.
    *
    * @Param $data
    *
    * @Returns   
 */
/* ----------------------------------------------------------------------------*/
function submitThisFeedback(array $data) : array 
{
    $res['success'] = true;
    $res['msg'] = '';
    try {
        $res['success'] = insertOrUpdateTable('course_feedback_responses'
            , 'login,question_id,year,semester,course_id,instructor_email,response'
            , 'year,semester,response', $data
        );
    } catch (Exception $e) {
        $res['msg'] .= $e->getMessage();
        $res['success'] = false;
    }
    return $res;
}


?>
