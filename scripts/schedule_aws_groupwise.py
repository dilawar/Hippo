#!/usr/bin/env python3

"""schedule_aws.py:

Query the database and schedule AWS.

TODO:
    Add summary of policy here.

"""

from __future__ import print_function

__author__           = "Dilawar Singh"
__copyright__        = "Copyright 2016, Dilawar singh <dilawars@ncbs.res.in>"
__credits__          = ["NCBS Bangalore"]
__license__          = "GNU GPL"
__version__          = "1.0.0"
__maintainer__       = "Dilawra Singh"
__email__            = "dilawars@ncbs.res.in"
__status__           = "Development/Production"

import sys
import os
import math
import numpy as np
from collections import defaultdict, OrderedDict, Counter
import datetime
import copy
import tempfile
from logger import _logger
from db_connect import db_
import networkx as nx
import random
import compute_cost
from global_data import *
import aws_helper


cwd = os.path.dirname( os.path.realpath( __file__ ) )
networkxPath = os.path.join( '%s/networkx' % cwd )
sys.path.insert(0, networkxPath )

_logger.info( 'Using networkx from %s' % nx.__file__ )
_logger.info( 'Using networkx from %s' % nx.__file__ )
_logger.info( 'Started on %s' % datetime.date.today( ) )

def short( line, maxchars = 4 ):
    words = list( filter( lambda x: x.split( ), line.split( ) ) )
    chars = [ x[0] for x in words ]
    return ''.join( chars )[:maxchars]

def init( cur ):
    """
    Create a temporaty table for scheduling AWS
    """

    global db_
    cur.execute( 'DROP TABLE IF EXISTS aws_temp_schedule' )
    cur.execute(
            '''
            CREATE TABLE IF NOT EXISTS aws_temp_schedule
            ( speaker VARCHAR(40) PRIMARY KEY, date DATE NOT NULL )
            '''
        )
    db_.commit( )
    cur.execute( "SELECT * FROM logins WHERE eligible_for_aws='YES' AND status='ACTIVE'" )
    for a in cur.fetchall( ):
        login = a[ 'login' ].lower( )
        speakers_[ login ] = a


    cur.execute( """SELECT * FROM holidays ORDER BY date""")
    for a in cur.fetchall( ):
        if a[ 'schedule_talk_or_aws' ] == 'NO':
            holidays_[ a['date'] ] = a

    cur.execute( """SELECT DISTINCT(specialization) FROM faculty""")
    for a in cur.fetchall( ):
        specialization_list_.append( a['specialization'] )

    _logger.info( 'Total speakers %d' % len( speakers_ ) )

def getPrevisousAWS( cur, student ):
    cur.execute( """
        SELECT * FROM annual_work_seminars WHERE speaker='%s' 
        ORDER BY DATE""" % student )

    res = cur.fetchall( )
    if res is None or len(res)==0:
        return None

    dates= [ ]
    for e in res:
        dates.append( e['date'] )
    return dates[-1]

def getAllAWSPlusUpcoming( ):
    global db_
    # cur = db_.cursor( cursor_class = MySQLCursorDict )
    try:
        cur = db_.cursor( dictionary = True )
    except Exception as e:
        print( e )
        print(
        '''If complain is about dictionary keyword. Install
        https://pypi.python.org/pypi/mysql-connector-python-rf/2.2.2
        using easy_install'''
        )
        quit( )

    init( cur )

    # Entries in this table are usually in future.
    cur.execute( 'SELECT * FROM upcoming_aws' )
    for a in cur.fetchall( ):
        aws_[ a[ 'speaker' ] ].append( a )
        upcoming_aws_[ a['speaker'].lower( ) ] = a['date']
        # Keep the number of slots occupied at this day.
        upcoming_aws_slots_[ a['date'] ].append( a['speaker'] )

    # Now get all the previous AWSs happened so far. Also fetch the
    # specialization of student depending on what student has specified or by
    # reading PI.
    cur.execute( 'SELECT * FROM annual_work_seminars' )
    for a in cur.fetchall( ):
        # If this speaker is not eligible anymore ignore.
        if a['speaker'] not in speakers_:
            continue

        aws_[ a[ 'speaker' ].lower() ].append( a )
        # Also get the specialization by reading the supervisor_1 .
        pi = a[ 'supervisor_1' ]
        if not pi:
            continue
        cur.execute( "SELECT specialization FROM faculty WHERE email='%s'" % pi )
        spec = cur.fetchone( )
        if spec:
            specialization = spec.get('specialization', 'UNSPECIFIED' )
            if specialization and specialization != 'UNSPECIFIED':
                speakersSpecialization_[ a['speaker'] ] = spec[ 'specialization' ]

    for a in aws_:
        # Sort a list in place.
        aws_[a].sort( key = lambda x : x['date'] )
        # print( a, [ x['date'] for x in aws_[a] ] )

    # Select all aws scheduling requests which have been approved.
    cur.execute( "SELECT * FROM aws_scheduling_request WHERE status='APPROVED'" )
    for a in cur.fetchall( ):
        aws_scheduling_requests_[ a[ 'speaker' ].lower( ) ] = a

    # Get specialization of each student. If no specified, fetch the
    # specialization of current PI.
    # IMP: This will overwrite the specialization fetched from previous AWS. It
    # is required.
    for st in speakers_:
        # If  this speaker has given AWS in less than 6 months, do not count her
        # when computing frequencies.
        prevAWSDate = getPrevisousAWS( cur, st )
        #if prevAWSDate is not None:
        #    if diffInDays( prevAWSDate, datetime.date.today( ) ) < 200:
        #        _logger.warn( 'Not counting %s. Recently given AWS' % st )
        #        continue

        cur.execute( "SELECT specialization FROM logins WHERE login='%s'" % st )
        a = cur.fetchone( )
        if not a[ 'specialization' ]:
            # Ok. Not specified; use faculty specialization.
            piOrHost = speakers_[ st ]['pi_or_host']

            if piOrHost:
                cur.execute( "SELECT specialization FROM faculty WHERE email='%s'" % piOrHost )
                a = cur.fetchone( )

        if a is not None and a[ 'specialization' ]:
            speakersSpecialization_[ st ] = a[ 'specialization' ]


    ## Compute the frequencies of specialization.
    ## Print specialization
    _logger.debug( 'Total speakers %d' % len( speakersSpecialization_ ) )
    freq = Counter( speakersSpecialization_.values( ) )
    for k in freq:
        specializationFreqs_[k] = 1.0 * freq[k] / sum( freq.values( ) )

    _logger.info( specializationFreqs_ )
    print( specializationFreqs_ )


def computeCost( speaker, slot_date, last_aws ):
    """ Here we are working with integers. With float the solution takes
    incredible large amount of time.
    """
    idealGap = 364
    nDays = ( slot_date - last_aws ).days
    nAws = len( aws_[speaker] )

    # If nDays is less than idealGap than cost function grows very fast. Use
    # weeks instead of months as time-unit.
    if( nDays <= idealGap ):
        # THere is no way, anyone should get AWS before idealGap. Return None so
        # that caller need not draw any edge.
        return None
    else:
        # Here we have two possibilities. Some speaker have not got their AWS
        # yet for quite a long time. Give preference to them. Reduce the cost to
        # very low but the cost should be larger for later AWS. if the
        # difference is 1.5 times the idealGap. If gap is more than 2.5 years,
        # than something is wrong with user. Ignore this profile and emit a
        # warning.
        fromToday = (datetime.date.today( ) - last_aws).days
        cost = compute_cost.computeCost( slot_date, last_aws, nAws )
        # print( speaker, slot_date, cost )
        return cost

# From  http://stackoverflow.com/a/3425124/1805129
def monthdelta(date, delta):
    m, y = (date.month+delta) % 12, date.year + ((date.month)+delta-1) // 12
    if not m: m = 12
    d = min(date.day, [31,
        29 if y%4==0 and not y%400==0 else 28,31,30,31,30,31,31,30,31,30,31][m-1])
    dt = date.replace(day=d,month=m, year=y)
    return dt

def diffInDays( date1, date2, absolute = False ):
    ndays = ( date1 - date2 ).days
    if absolute:
        ndays = abs( ndays )
    return ndays


def get_prev_aws_date( speaker ):

    lastDate = None

    if speakersSpecialization_.get( speaker, 'UNSPECIFIED' ) == 'UNSPECIFIED':
        _logger.warning( "Could not find specialization for %s" % speaker )

    # Last entry is most recent
    if speaker not in aws_.keys( ):
        # We are here because this speaker has not given any AWS yet.
        freshers_.add( speaker )

        # If this user has PHD/POSTDOC, or INTPHD title. We create  a dummy
        # last date to bring her into potential speakers.
        # First make sure, I have their date of joining. Otherwise I
        # can't continue. For MSc By Research/INTPHD assign their first AWS
        # after 18 months. For PHD and POSTDOC, it should be after 12 months.
        if speakers_[ speaker ]['title'] == 'INTPHD':
            # InPhd should get their first AWS after 15 months of
            # joining.
            _logger.info( '%s = INTPHD with 0 AWS so far' % speaker )
            joinDate = speakers_[ speaker ]['joined_on']
            if not joinDate:
                _logger.warn( "Could not find joining date" )
            else:
                lastDate = monthdelta( joinDate, +6 )

        if speakers_[ speaker ]['title'] == 'MSC':
            # MSc should get their first AWS after 18 months of
            # joining. Same as INTPHD
            _logger.info( '%s = MSC BY RESEARCH with 0 AWS so far' % speaker )
            joinDate = speakers_[ speaker ]['joined_on']
            if not joinDate:
                _logger.warn( "Could not find joining date" )
            else:
                lastDate = monthdelta( joinDate, +6 )

        elif speakers_[ speaker ]['title'] in [ 'PHD', 'POSTDOC' ]:
            joinDate = speakers_[ speaker ]['joined_on']
            _logger.info( '%s PHD/POSTDOC with 0 AWS so far' % speaker )
            if not joinDate:
                _logger.warn( "Could not find joining date" )
            else:
                try:
                    # if datetime.
                    lastDate = joinDate.date( )
                except Exception as e:
                    # Else its date
                    lastDate = joinDate
    else:
        # We are here because this speaker has given AWS before
        # If this speaker is already on upcoming AWS list, ignore it.
        if speaker in upcoming_aws_:
            _logger.info(
                    'Speaker %s is already scheduled on %s' % (
                        speaker, upcoming_aws_[ speaker ]
                        )
                    )
            return None
        # If this speakers is MSC by research and has given AWS before, she/he
        # need not give another.
        elif speakers_[ speaker ]['title'] == 'MSC':
            _logger.info( '%s is MSC and has given AWS in the past' % speaker )
            return None
        else:
            lastDate = aws_[speaker][-1]['date']
    return lastDate


def afterNDays( date, ndays ):
    return date + datetime.timedelta( days = ndays )

def chooseSpecialization( n, seed = 0 ):
    random.seed( seed )
    np.random.seed( seed )
    ps = list(specializationFreqs_.values( ))
    return np.random.choice( list(specializationFreqs_.keys( )), size = n, p=ps)

def construct_graph( validSlots ):

    lastDate = None
    for i, speaker in enumerate( speakers_ ):
        lastDate = get_prev_aws_date( speaker )
        # If a speaker has a lastDate either because he has given AWS in the
        # past or becuase she is fresher. Create an edge.
        if lastDate is not None:
            g_.add_node( speaker, last_date = lastDate, pos = (1, 3*i) )
            g_.add_edge( 'source', speaker, capacity = 1, weight = 0 )


    for sid, monday, specForWeek in validSlots:
        # For each Monday, we have 3 AWS - (assigned on upcoming_aws_slots_)
        # For each week select a specialization.
        _logger.info( "++ Specialization for this week is %s" % specForWeek )
        g_.add_node( sid, date = monday, specialization = specForWeek)
        g_.add_edge( sid, 'sink', capacity = 1, weight = 0 )

    # Now for each student, add potential edges.
    idealGap = 357

    freshersDate = defaultdict( list )
    for speaker in speakers_:
        speakerSpecialization = speakersSpecialization_.get( speaker, '' )
        preferences = aws_scheduling_requests_.get( speaker, {} )

        if preferences:
            _logger.info( "%s has preferences %s " % (speaker,preferences) )

        if speaker not in g_.nodes( ):
            _logger.info( 'Nothing for user %s' % speaker )
            continue

        prevAWSDate = g_.node[ speaker ][ 'last_date' ]
        for slot, monday, speci in validSlots:
            # If this slot does not belong to some specialization then ignore
            # it.
            if g_.node[ slot ]['specialization'] != speakerSpecialization:
                continue

            date = g_.node[ slot ][ 'date' ]
            weight = computeCost( speaker, date, prevAWSDate )
            if weight is None:
                continue

            # If the speaker is fresher, do not draw edges to all three
            # slots. Draw just one but make sure that they get this slot. We
            # reduce the cost to almost zero.
            if speaker in freshers_:
                # Let two freshers take maximum of two slots on same day.
                # The weight should be low but not lower than user
                # preference.
                if freshersDate.get(speaker, []).count( date ) < 2:
                    addEdge(speaker, slot, 1, 5 )
                    # This date is taken by this fresher.
                    freshersDate[ speaker ].append( date )
            else:
                addEdge(speaker, slot, 1, weight )

            # Honour user preferences..
            if preferences:
                first = preferences.get( 'first_preference', None )
                second = preferences.get( 'second_preference', None )
                if first:
                    ndays = diffInDays(date, first, True)
                    if ndays <= 14:
                        _logger.debug( 'Using first preference for %s' % speaker )
                        addEdge(speaker, slot, 1, 0 + ndays / 7 )
                if second:
                    ndays = diffInDays(date, second, True)
                    if ndays <= 14:
                        _logger.info( 'Using second preference for %s' % speaker )
                        addEdge(speaker, slot, 1, 2 + ndays / 7 )

    # Each slot node must have at least 3 nodes.
    missedSlots = [ ]
    for slot, monday, speci in validSlots:
        inDegree = g_.in_degree( slot )
        inedges = g_.predecessors( slot )
        if inDegree < 1:
            _logger.warn( "slot %s [%s] have no options" % (slot, speci ))
            missedSlots.append( slot )

    _logger.info( 'Constructed flow graph' )
    return missedSlots



def construct_flow_graph( seed = 0 ):
    """This is the most critical section of this task. It is usually good if
    flow graph is constructed to honor policy as much as possible rather than
    fixing the solution later.

    One important scheduling aim is to minimize number of freshers on the same
    day. Ideally no more than 1 freshers should be allowed on same day. This can
    be achieved by modifying the solution later : swapping freshers with
    experienced speaker from other days. We avoid that by drawing two edges
    from freshers to a 'date' i.e. maximum of 2 slots can be filled by freshers.
    For others we let them fill all three slots.

    4 slots every monday and all belong to one specialization.
    """

    g_.clear( )

    g_.add_node( 'source', pos = (0,0) )
    g_.add_node( 'sink', pos = (10, 10) )

    # Compute totalWeeks of schedule starting today.
    totalWeeks = 32
    d = datetime.date.today()
    while d.weekday() != 0: # 0 is monday
        d += datetime.timedelta( days = 1 )
    nextMonday = d
    slots = []

    weeks = [ afterNDays( nextMonday, 7*i ) for i in range( totalWeeks ) ]

    specializations = chooseSpecialization( totalWeeks, seed )

    # Ignore the already filled upcoming AWS slots.
    freq = Counter( speakersSpecialization_.values( ) )
    for monday in sorted(upcoming_aws_slots_):
        speakers = upcoming_aws_slots_[ monday ]
        for speaker in speakers:
            specialization = speakersSpecialization_.get(speaker, 'UNSPECIFIED')
            freq[ specialization ] = max(0, freq[specialization]-1)

    # Update the frequencies.
    for k in freq:
        specializationFreqs_[k] = 1.0 * freq[k] / sum( freq.values( ) )

    # Collect all the valid slots with specialization in a list.
    validSlots = [ ]
    for specForWeek, monday in zip( specializations, weeks ):
        # AWS don't care about holidays.
        if monday in holidays_:
           _logger.warn( "This date %s is holiday" % monday )
           continue

        nSlots = 3
        if monday in upcoming_aws_slots_:
            # Check how many of these dates have been taken.
            _logger.info( 'Date %s is taken ' % monday )
            nSlots -= len( upcoming_aws_slots_[ monday ] )
            for i in range( nSlots ):
                validSlots.append( ('%s,%s' % (monday,i),monday,specForWeek) )
        else:
            validSlots += [ ('%s,%s' % (monday,i),monday,specForWeek) for i in range(nSlots) ]

    # Keep edges from freshers to dates here. We allow maximum of 2 out of 3
    # slots to be taken by freshers (maximum ).
    missed = construct_graph( validSlots  )
    return missed

def addEdge( speaker, slot, capacity, weight ):
    """Create an edge between speaker and slot.

    TODO: Is it a good idea to make sure that speakers which were grouped last
    time, do not get to group this time. One way is to add a relatively small
    random number to the cost. Other is to check before adding the edge. Let's
    just use the slot index to increase the weight.

    """
    whichSlot = int( slot.split( ',' )[-1] )
    g_.add_edge( speaker, slot, capacity = 1, weight = weight + whichSlot )

def write_graph( outfile  = 'network.dot' ):
    # Convert datetime to string before writing to file.
    # This operation should be done at the very end.
    # This operation should be done at the very end.
    dotText = [ "digraph G { " ]
    for n in g_.nodes():
        nodeText = '\t"%s" [' % n
        for attr in g_.node[ n ]:
            nodeText += '%s="%s", ' % (attr, g_.node[n][attr] )
        nodeText += ']'
        dotText.append( nodeText )

    for s, t in g_.edges( ):
        edgeText = ( '\t "%s" -> "%s" [' % (s, t) )
        for attr in g_[s][t]:
            edgeText += '%s="%s",' % (attr, g_[s][t][attr] )
        edgeText += ']'
        dotText.append( edgeText )

    dotText.append( "}" )
    with open( outfile, "w" ) as f:
        f.write( "\n".join( dotText ) )

def test_graph( graph ):
    """Test that this graph is valid """
    # Each edge must have a capcity and weight
    for u, v in graph.edges():
        if 'capacity' not in  graph[u][v]:
            _logger.info( 'Error: %s -> %s no capacity assigned' % (u, v) )
        if 'weight' not in  graph[u][v]:
            _logger.info( 'Error: %s -> %s no weight assigned' % (u, v) )
    _logger.info( '\tDone testing graph' )

def neighouringSlots( date, allSlots, weeks ):
    fmt = '%Y-%m-%d'
    d = datetime.datetime.strptime( date, fmt )
    dates = [ d + datetime.timedelta( days = 7 * x ) for x in weeks ]
    dates = [ datetime.datetime.strftime(x, fmt) for x in dates ]
    slots = [ ]
    for d in dates:
        slots += [ '%s,%d' % (d, x) for x in range(3) ]

    # Return only valid slots
    return filter( lambda x : x in allSlots, slots )


def potentialSpeakerToSwap( speaker, date, candidates
        , already_swapped = [ ], low = -21, high = 21 ):
    """
    Find good candidate to swap. Their AWS should not far from speaker

    already_swapped list contains the list of speaker who are already swapped
    with someone else.
    """

    swapWith = [ ]
    for speaker2, date2 in candidates.iteritems( ):
        d2, d = [ datetime.datetime.strptime( x, fmt_ ) for x in [ date2, date] ]
        diffDays = (d2 - d).days
        if diffDays >= low and diffDays <= high:
            if speaker2 in already_swapped:
                continue
            swapWith.append( (abs(diffDays), speaker2) )

    # Return the lowest cost candidate.
    if swapWith:
        # Return the lowest cost candidate
        return swapWith[0][1]

    return None

def swapInSchedule( a, b, schedule ):
    """Swap speaker a with speaker b in schedule """
    for date, speakers in schedule.iteritems( ):
        if a in speakers:
            speakers.remove( a )
            speakers.append( b )
        elif b in speakers:
            speakers.remove( b )
            speakers.append( a )

def fresherIndex( schedule ):
    freshers = [ ]
    for date in sorted( schedule ):
        speakers = schedule[ date ]
        nFreshers = 0
        for i, speaker in enumerate( speakers ) :
            naws = len( aws_.get( speaker, [] ) )
            if naws == 0:
                nFreshers += 1
        freshers.append( nFreshers )
    return np.std( freshers )

def swapSpeakers( speakersA, speakersB, schedule, low = -21, high = 21):
    """Swap speakersA with speakersB
    """
    alreadySwapped = [ ]
    for speaker, date in speakersA.iteritems( ):
        swapWith = potentialSpeakerToSwap( speaker, date, speakersB
                , alreadySwapped, low, high
                )
        if swapWith:
            _logger.info( 'Swapping %s with %s' % ( speaker, swapWith ) )
            alreadySwapped.append( swapWith )
            swapInSchedule( speaker, swapWith, schedule )

    _logger.info( "After swapping %f" % fresherIndex( schedule ) )
    return schedule

def avoidClusteringOfFreshers( schedule ):
    """
    Make sure not all student are freshers. Also try to put at least 1
    fresher.

    THIS FUNCTION IS DEPRECATED.:
    We almost achieved same assignment by drawing at most 2 edges from fresheres
    to same date.

    """

    _logger.info( "Before swapping %f" % fresherIndex( schedule ) )

    # 1: SWAP one in rows with all experienced with a fresher.
    # Store speaker to move in this dict. Freshers should not move too much
    # therefore we do not change the default low and high parameters.
    speakersToSwap = OrderedDict( )
    candidates = OrderedDict( )
    for date in sorted( schedule ):
        nAws = { }
        speakers = schedule[ date ]
        nFreshers = 0
        for i, speaker in enumerate( speakers ) :
            naws = len( aws_.get( speaker, [] ) )
            if naws == 0:
                nFreshers += 1
            if nFreshers > 1:
                # Take this speaker from here and put it into to swap with list.
                candidates[ speaker ] = date

        # If all of them are experienced, then I can take one of them ( to be
        # replace by fresher) and put him/her into to be moved list.
        if nFreshers == 0:
            speakersToSwap[ speakers[0] ] = date
    # Since we really do not want mutliple freshers on same day, we search wide
    # for swapping. We can delay AWS of senior students.
    schedule = swapSpeakers( speakersToSwap, candidates, schedule
            , low = -91 , high = 28
            )


    # 2 : SWAP two in rows which have all freshers with experienced
    # Store speaker to move in this dict.
    speakersToSwap = { }
    candidates = { }
    for date in sorted( schedule ):
        nAws = { }
        speakers = schedule[ date ]
        nFreshers = 0
        for i, speaker in enumerate( speakers ) :
            naws = len( aws_.get( speaker, [] ) )
            if naws == 0:
                nFreshers += 1
            if nFreshers > 1:
                speakersToSwap[ speaker ] = date

        # If no of freshers are zero, I can put 1 speaker into swap list.
        if nFreshers == 0:
            candidates[ speakers[0] ] = date

    schedule = swapSpeakers( speakersToSwap, candidates, schedule)

    return schedule


def getMatches( res ):
    """
    In this case residue is date in values and not in keys. Compare with
    getMatches
    """

    result = defaultdict( list )
    for u in res:
        if u in [ 'sink', 'source']:
            continue
        for v in res[u]:
            if v in [ 'source', 'sink' ]:
                continue
            f = res[u][v]
            if f > 0:
                date, slot = v.split(',')
                result[date].append( u )
    return result

def computeSchedule( ):
    _logger.info( 'Scheduling AWS now' )
    test_graph( g_ )
    _logger.info( 'Computing max-flow, min-cost' )
    res = nx.max_flow_min_cost( g_, 'source', 'sink' )
    _logger.info( '\t Computed. Getting schedules now ...' )
    sch = getMatches( res )
    return sch


def findSpeakerWithSpecialization( specialization, after_date, not_in_these_labs, schedule ):
    for dateA in sorted( schedule ):
        if dateA <= after_date:
            continue
        for i, speakerA in enumerate( schedule[dateA] ):
            slot = '%s,%d' % (dateA,i)
            spec = g_.node[slot]['specialization']
            if spec != specialization:
                continue
            thisPI = speakers_[ speakerA ]['pi_or_host']
            if thisPI in not_in_these_labs:
                continue
            if len( aws_[ speakerA ] ) < 2:
                continue
            return speakerA, dateA
    return None

def print_schedule( schedule, outfile ):
    with open( outfile, 'w' ) as f:
        f.write( "This is what we got \n" )

    cost = 0
    for date in  sorted(schedule):
        line = "%s :" % date
        totalFreshers = 0
        for speaker in schedule[ date ]:
            line += '%13s (%10s, %1d) %4s' % (speaker
                , g_.node[speaker]['last_date'].strftime('%Y-%m-%d')
                , len( aws_[ speaker ] )
                , short( speakersSpecialization_[speaker])
                )
            if len( aws_[speaker] ) == 0:
                totalFreshers += 1
            cost += totalFreshers
        line += ',%d' % totalFreshers

        with open( outfile, 'a' ) as f:
            f.write( '%s\n' % line )
            print( line )
    print( 'Total freshers %d' % cost )

def commit_schedule( schedule ):
    global db_
    cur = db_.cursor( )
    _logger.info( 'Committing computed schedules ' )
    for date in sorted(schedule):
        for speaker in schedule[date]:
            query = """
                INSERT INTO aws_temp_schedule (speaker, date) VALUES ('{0}', '{1}')
                ON DUPLICATE KEY UPDATE date='{1}'
                """.format( speaker, date )
            # _logger.debug( query )
            cur.execute( query )
    db_.commit( )
    _logger.info( "Committed to database" )

def main( outfile ):
    global db_
    _logger.info( 'Scheduling AWS' )
    getAllAWSPlusUpcoming( )
    ans = None
    construct_flow_graph( )
    ans = computeSchedule( )
    ans = aws_helper.no_common_labs( ans )
    try:
        print_schedule( ans, outfile )
    except Exception as e:
        _logger.error( "Could not print schedule. %s" % e )

    if ans:
        commit_schedule( ans )
    else:
        print( 'Failed to compute schedule' )
        return -1
    try:
        write_graph( )
    except Exception as e:
        _logger.error( "Could not write graph to file" )
        _logger.error( "\tError was %s" % e )
    db_.close( )

if __name__ == '__main__':
    outfile = tempfile.NamedTemporaryFile( ).name
    if len( sys.argv ) > 1:
        outfile = sys.argv[1]
    main( outfile )
