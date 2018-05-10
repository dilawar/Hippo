<?php

require_once BASEPATH.'autoload.php';

// This script may also be called by command line by the email bot. To make sure 
// $_GET works whether we call it from command line or browser.
if( isset($argv) )
    parse_str( implode( '&' , array_slice( $argv, 1 )), $_GET );


function awsToTex( $aws )
{
    // First sanities the html before it can be converted to pdf.
    foreach( $aws as $key => $value )
    {
        // See this 
        // http://stackoverflow.com/questions/9870974/replace-nbsp-characters-that-are-hidden-in-text
        $value = htmlentities( $value, null, 'utf-8' );
        $value = str_replace( '&nbsp;', '', $value );
        $value = preg_replace( '/\s+/', ' ', $value );
        $value = html_entity_decode( trim( $value ) );
        $aws[ $key ] = $value;
    }

    $speaker = __ucwords__( loginToText( $aws[ 'speaker' ] , false ));

    $supervisors = array( __ucwords__( 
        loginToText( findAnyoneWithEmail( $aws[ 'supervisor_1' ] ), false ))
                ,  __ucwords__( 
        loginToText( findAnyoneWithEmail( $aws[ 'supervisor_2' ] ), false ))
            );
    $supervisors = array_filter( $supervisors );

    $tcm = array( );
    array_push( $tcm, __ucwords__( 
        loginToText( findAnyoneWithEmail( $aws[ 'tcm_member_1' ] ), false ))
            , __ucwords__( 
        loginToText( findAnyoneWithEmail( $aws[ 'tcm_member_2' ] ), false ))
            ,  __ucwords__( 
        loginToText( findAnyoneWithEmail( $aws[ 'tcm_member_3' ] ), false ))
            , __ucwords__( 
        loginToText( findAnyoneWithEmail( $aws[ 'tcm_member_4' ] ), false ))
        );
    $tcm = array_filter( $tcm );

    $title = $aws[ 'title' ];
    if( strlen( trim( $title ) ) < 1 )
        $title = 'Not disclosed yet!';

    $abstract = $aws[ 'abstract' ];
    if( strlen( trim( $abstract ) ) < 1 )
        $abstract = 'Not disclosed yet!';

    // Add user image.
    $imagefile = getLoginPicturePath( $aws['speaker'], 'hippo' );
    $imagefile = getThumbnail( $imagefile );


    // Date and plate
    $date = '\faCalendarCheckO\, \textsc{\bf \textcolor{red}{ ' . humanReadableDate( $aws[ 'date' ] ) .  ' | 4:00 pm' . '}}';
    $place = '\faHome\, \textsc{\bf \textcolor{red}{Haapus (LH1), Eastern Lab Complex}}';

    // Two columns here.
    $head = '';
    $logo = FCPATH.'data/ncbs_logo.png';

    // Is presynopsis seminar?
    if( __get__( $aws, 'is_presynopsis_seminar', 'NO' ) == 'YES' )
        $awsType = 'Presynopsis Seminar';
    else
        $awsType = 'Annual Work Seminar';

    // Header 
    $head .= '\begin{tikzpicture}[remember picture, overlay
        , every node/.style={rectangle, node distance=5mm,inner sep=0mm} ]';
    $head .= '\node[below=of current page.north west, anchor=north west, xshift=10mm] (logo) 
        {\includegraphics[height=1cm]{' . $logo . '}};';
    $head .= '\node[below=of current page.north east, anchor=north east,xshift=-10mm] (aws) 
            {\LARGE \textbf{\textsc{' . $awsType . '}}};';
    $head .= '\node[below=of aws.south west,anchor=north west] (date) { ' . $date . ' }; ';
    $head .= '\node[below=of date.west,anchor=west] (place) { ' . $place . ' }; ';
    $head .= '\node[below=of place] (place1) {};';
    $head .= '\node[fit=(current page.north east) (current page.north west) (place1)
            , rectangle, fill=blue, opacity=0.2] (header) { };';
    $head .= '\end{tikzpicture}';

    $speakerImg = '\includegraphics[height=45mm,trim=2 2 2 2,clip]{' . $imagefile . '}';
    $head .= '\par \vspace{10mm} \par ';
    // $head .= '\begin{tikzpicture}[overlay, every node/.style={rectangle, node distance=5mm,inner sep=0mm} ]';
    // $head .= '\node[yshift=-25mm] (img) { ' . $speakerImg . '};';
    $head .= '\begin{tikzpicture}[ ]';
    $head .= '\node[ ] (img) { ' . $speakerImg . '};';
    $head .= '\node[right=of img,text width=0.65\linewidth] (title) {{\LARGE ' . $title . '}};';
    $head .= '\node[below=of title.south west, anchor=west] (author) {\textbf{' . $speaker . '}};';
    $head .= '\end{tikzpicture}';

    // Header
    $tex = array( $head );
    $tex[] = '\par \vspace{3mm}';

    // remove html formating before converting to tex.
    $tempFile = tempnam( sys_get_temp_dir(), "hippo_abstract" );
    file_put_contents( $tempFile, $abstract );

    $cmd = FCPATH.'scripts/html2other.py';
    hippo_shell_exec( "$cmd $tempFile tex", $texAbstractFile, $stderr );

    $texAbstract = file_get_contents( trim($texAbstractFile) );
    // unlink( $tempFile );

    if( strlen(trim($texAbstract)) > 10 )
        $abstract = $texAbstract;

    // Title and abstract
    $extra = '\begin{tabular}{ll}';
    $extra .= '\textbf{Supervisor(s)} & ' . implode( ",", $supervisors) . '\\\\';
    $extra .= '\textbf{Thesis Committee Member(s)} & ' . implode( ", ", $tcm ) . '\\\\';
    $extra .= '\end{tabular}';

    $tex[] = '\begin{tcolorbox}[colframe=black!0,colback=red!0
        , fit to height=18 cm, fit basedim=14pt, enhanced]
        \fontfamily{pnc}\selectfont
    ' . $abstract . '\vspace{5mm}' . '{\normalsize \vfill ' . $extra . '} \end{tcolorbox}';

    return implode( "\n", $tex );

} // Function ends.

function pdfFileOfAWS( string $date, string $speaker = '' ) : string
{
    if( ! $date )
    {
        echo printWarning( "Invalid date $date" );
        return '';
    }
    
    $whereExpr = "date='" . $date . "'";
    if( $speaker )
        $whereExpr .= " AND speaker='$speaker'";

    $awses = getTableEntries( 'annual_work_seminars', '', $whereExpr );
    $upcomingS = getTableEntries( 'upcoming_aws', '', $whereExpr ); 
    $awses = array_merge( $awses, $upcomingS );

    // Intialize pdf template.
    $tex = array( "\documentclass[]{article}"
        , "\usepackage[margin=25mm,top=20mm,a4paper]{geometry}"
        , "\usepackage[]{graphicx}"
        , "\usepackage[]{grffile}"
        , "\usepackage[]{amsmath,amssymb}"
        , "\usepackage[]{color}"
        , "\usepackage{tikz}"
        , "\usepackage{wrapfig}"
        , "\usepackage{fontawesome}"
        , '\pagenumbering{gobble}'
        , '\linespread{1.2}'
        , '\usetikzlibrary{calc,positioning,arrows,fit}'
        // , '\usepackage{ebgaramond}'
        , '\usepackage[sfdefault,light]{FiraSans}'
        , '\usepackage{tcolorbox}'
        , '\tcbuselibrary{fitting}'
        , '\begin{document}'
        );

    $outfile = 'AWS_' . $date;
    foreach( $awses as $aws )
    {
        $outfile .= '_' . $aws[ 'speaker' ];
        $tex[] = awsToTex( $aws );
        $tex[] = '\newpage';
    }

    $tex[] = '\end{document}';
    $TeX = implode( "\n", $tex );

    // Generate PDF now.
    $texFile = sys_get_temp_dir() . "/$outfile.tex";

    // Remove tex from the end and apped pdf.
    $pdfFile = rtrim( $texFile, 'tex' ) . 'pdf';

    if( file_exists( $pdfFile ) )
        unlink( $pdfFile );

    file_put_contents( $texFile,  $TeX );

    $cmd = FCPATH."scripts/tex2pdf.sh $texFile";

    if( file_exists( $texFile ) )
        hippo_shell_exec( "nohup $cmd", $stdout, $stderr );

    if( file_exists($pdfFile) )
        return $pdfFile;

    alertUser( "Failed to genered pdf document <br>
        This is usually due to hidden special characters 
        in your abstract. You need to clean your entry up." 
        );
    return '';
}

?>
