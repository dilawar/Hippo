<?php

require_once BASEPATH. 'database.php';
require_once BASEPATH.'extra/tohtml.php';

// This script may also be called by command line by the email bot. To make sure 
// $_GET works whether we call it from command line or browser.
if( isset($argv) )
    parse_str( implode( '&' , array_slice( $argv, 1 )), $_GET );

function get_suitable_font_size( $desc )
{
    $nchar = strlen( $desc );
    if( $nchar > 1900 )
        return '14pt';
    return '12pt';
}

function eventToTex( $event, $talk = null )
{
    // First sanities the html before it can be converted to pdf.
    foreach( $event as $key => $value )
    {
        // See this 
        // http://stackoverflow.com/questions/9870974/replace-nbsp-characters-that-are-hidden-in-text
        $value = htmlentities( $value, null, 'utf-8' );
        $value = str_replace( '&nbsp;', '', $value );
        $value = preg_replace( '/\s+/', ' ', $value );
        $value = html_entity_decode( trim( $value ) );
        $event[ $key ] = $value;
    }

    // Crate date and plate.
    $where = venueSummary( $event[ 'venue' ] );
    $when = humanReadableDate( $event['date'] ) . ' | ' . humanReadableTime( $event[ 'start_time' ] );

    $title = $event[ 'title' ];
    $desc = $event[ 'description' ];


    // Prepare speaker image.
    $imagefile = getSpeakerPicturePath( $talk[ 'speaker_id' ] );
    if( ! file_exists( $imagefile ) )
        $imagefile = nullPicPath( );

    // Add user image.
    $imagefile = getThumbnail( $imagefile );
    $speakerImg = '\includegraphics[width=4cm]{' . $imagefile . '}';

    $speaker = '';
    if( $talk )
    {
        $title = $talk['title'];
        $desc = fixHTML( $talk[ 'description' ] );

        // Get speaker if id is valid. Else use the name of speaker.
        if( intval( $talk[ 'speaker_id' ] ) > 0 )
            $speakerHTML = speakerIdToHTML( $talk['speaker_id' ] );
        else 
            $speakerHTML = speakerToHTML( getSpeakerByName( $talk[ 'speaker' ]));

        $speaker = html2Tex( $speakerHTML );
    }


    // Header
    $head = '';

    // Put institute of host in header as well
    $isInstem = false;
    $inst = emailInstitute( $talk['host'], "latex" );
    if( strpos( strtolower( $inst ), 'institute for stem cell' ) !== false )
        $isInstem = true;

    $logo1 = '';
    if( $isInstem )
        $logo1 = '\includegraphics[height=2.5cm]{' . FCPATH  . '/data/inStem_logo.png}';
    else
        $logo1 = '\includegraphics[height=2.5cm]{' . FCPATH . '/data/ncbs_logo.png}';

    $logo2 = '';
    if( __get__($talk, 'host_extra', ''))
    {
        $isInstem = false;
        $inst = emailInstitute( $talk['host_extra'], "latex" );
        if( strpos( strtolower( $inst ), 'institute for stem cell' ) !== false )
            $isInstem = true;
        if( $isInstem )
            $logo2 = '\includegraphics[height=2.5cm]{' . FCPATH  . '/data/inStem_logo.png}';
        else
            $logo2 = '\includegraphics[height=2.5cm]{' . FCPATH . '/data/ncbs_logo.png}';
    }


    // Logo etc.
    $date = ' ,' .  $when;
    $place = ' ,' . $where;

    $head .= '\begin{tikzpicture}[remember picture,overlay
        , every node/.style={rectangle, node distance=5mm,inner sep=0mm} ]';

    $head .= '\node[below=of current page.north west,anchor=west,shift=(-45:1.5cm)] (logo1) { ' . $logo1 . '};';
    if($logo2)
        $head .= '\node[right=of logo1.south east, anchor=south west] (logo2) { ' . $logo2 . '};';

    $head .= '\node[below=of current page.north east,anchor=south east,shift=(-135:1.5cm)] (tclass) 
            {\LARGE \textsc{\textbf{' . $talk['class'] . '}}};';
    $head .= '\node[below=of tclass.south east, anchor=east] (date) {\small \textsc{' . $date . '}};';
    $head .= '\node[below=of date.south east, anchor=south east] (place) {\small \textsc{' . $place . '}};';
    $head .= '\node[below=of place] (place1) {};';
    $head .= '\node[fit=(current page.north east) (current page.north west) (place1)
                    , fill=red, opacity=0.3, rectangle, inner sep=1mm] (fit_node) {};';
    $head .= '\end{tikzpicture}';
    $head .= '\par \vspace{5mm} ';

    $head .= '\begin{tikzpicture}[ ]';
    $head .= '\node[inner sep=0, inner sep=0pt] (image) {' . $speakerImg . '};';
    $head .= '\node[right=of image.north east, anchor=north west, text width=0.6\linewidth] (title) { ' .  '{\Large ' . $title . '} };';
    $head .= '\node[below=of title,text width=0.6\linewidth,yshift=10mm] (author) { ' .  '{\small ' . $speaker . '} };';
    $head .= '\end{tikzpicture}';
    $head .= '\par'; // So tikzpicture don't overlap.

    $tex = array( $head );

    $tex[] = '\par';
    file_put_contents( '/tmp/desc.html', $desc );
    $texDesc = html2Tex( $desc ); 
    if( strlen(trim($texDesc)) > 10 )
        $desc = $texDesc;

    $extra = '';
    if( $talk )
    {
        $extra .= '\newline \vspace{1cm} \vfill';
        $extra .= "\begin{tabular}{ll}\n";
        $extra .= '{\bf Host} & ' . html2Tex( loginToHTML($talk[ 'host' ]) );

        // Add extra host if available.
        if(__get__($talk, 'host_extra', ''))
            $extra .= ' and ' . html2Tex( loginToHTML($talk[ 'host_extra' ]));
        $extra .= '\\\\';

        if( $talk[ 'coordinator' ] )
            $extra .= '{\bf Coordinator} & ' . html2Tex( loginToHTML($talk[ 'coordinator' ]));
        $extra .=  '\\\\';
        $extra .= '\end{tabular}';
    }

    $tex[] = '\begin{tcolorbox}[colframe=black!0,colback=red!0
        , fit to height=17 cm, fit basedim=16pt
        ]' . $desc . $extra . '\end{tcolorbox}';

    $texText = implode( "\n", $tex );
    return $texText;

} // Function ends.


function generatePdfForTalk( string $date, string $id = '' ) : string
{

    ///////////////////////////////////////////////////////////////////////////////
    // Intialize pdf template.
    //////////////////////////////////////////////////////////////////////////////
    // Institute 
    $tex = array( 
        "\documentclass[12pt]{article}"
        , "\usepackage[margin=25mm,top=3cm,a4paper]{geometry}"
        , "\usepackage[]{graphicx}"
        , "\usepackage[]{wrapfig}"
        , "\usepackage[]{grffile}"
        , "\usepackage[]{amsmath,amssymb}"
        , "\usepackage[colorlinks=true]{hyperref}"
        , "\usepackage[]{color}"
        , "\usepackage{tikz}"
        , '\linespread{1.15}'
        , '\pagenumbering{gobble}'
        , '\usetikzlibrary{fit,calc,positioning,arrows,backgrounds}'
        , '\usepackage[sfdefault,light]{FiraSans}'
        , '\usepackage{tcolorbox}'          // Fit text in one page.
        , '\tcbuselibrary{fitting}'
        , '\begin{document}'
    );

    $ids = array( );
    if( $id )
        $ids[] = $id;
    else if( $date )
    {
        // Not all public events but only talks.
        $entries = getPublicEventsOnThisDay( $date );
        foreach( $entries as $entry )
        {
            $eid = explode( '.', $entry[ 'external_id' ] );
            // Only from table talks.
            if( $eid[0] == 'talks' && intval( $eid[1] ) > 0 )
                $ids[] =$eid[1];
        }
    }
    else
    {
        echo alertUser( 'Not valid id or date found.' );
        return '';
    }

    // Prepare TEX document.
    $outfile = 'EVENTS';
    if( $date )
        $outfile .= '_' . $date;

    foreach( $ids as $id )
    {
        $talk = getTableEntry( 'talks', 'id', array( 'id' => $id ) );
        $event = getEventsOfTalkId( $id );
        $tex[] = eventToTex( $event, $talk );
        $tex[] = '\pagebreak';
        $outfile .= "_$id";
    }

    $tex[] = '\end{document}';
    $TeX = implode( "\n", $tex );

    // Generate PDF now.
    $outdir = sys_get_temp_dir();
    $pdfFile = $outdir . '/' . $outfile . ".pdf";
    $texFile = sys_get_temp_dir() . '/' . $outfile . ".tex";

    if( file_exists( $pdfFile ) )
        unlink( $pdfFile );

    file_put_contents( $texFile,  $TeX );
    $cmd = FCPATH . "scripts/tex2pdf.sh $texFile";
    if( file_exists( $texFile ) )
        hippo_shell_exec($cmd, $res, $stderr);

    if( file_exists( $pdfFile ) )
        return $pdfFile;

    alertUser( "Failed to genered pdf document. <br />
        This is usually due to hidden special characters 
        in your text. You need to cleanupyour entry."
        );
    return '';
}

?>
