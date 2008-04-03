<?php 
/******************************************************************************
 * Links auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * start     - Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Links steht
 *             (Default) Links
 * id        - Nur einen einzigen Link anzeigen lassen.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/bbcode.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
elseif($g_preferences['enable_weblinks_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require("../../system/login_valid.php");
}

// Uebergabevariablen pruefen

if (array_key_exists("start", $_GET))
{
    if (is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["start"] = 0;
}

if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["id"] = 0;
}

if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Weblinks";
}

if ($g_preferences['enable_bbcode'] == 1)
{
    // Klasse fuer BBCode initialisieren
    $bbcode = new ubbParser();
}

// Navigation initialisieren - Modul faengt hier an.
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

unset($_SESSION['links_request']);

// Hier eingerichtet, damit es spaeter noch in den Orga-Einstellungen verwendet werden kann
$linksPerPage = 10;

// Html-Kopf ausgeben
$g_layout['title'] = $_GET["headline"];
if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] =  "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Links\"
        href=\"$g_root_path/adm_program/modules/links/rss_links.php\" />";
};

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'. $_GET["headline"]. '</h1>
<div id="links_overview">';

// falls eine id fuer einen bestimmten Link uebergeben worden ist...
if ($_GET['id'] > 0)
{
    $sql1 = "SELECT * FROM ". TBL_LINKS. ", ". TBL_CATEGORIES ."
             WHERE lnk_id = ". $_GET['id']. "
             AND lnk_cat_id = cat_id
             AND cat_org_id = ". $g_current_organization->getValue("org_id");
}
//...ansonsten alle fuer die Gruppierung passenden Links aus der DB holen.
else
{
    // Links bereits nach den Namen ihrer Kategorie sortiert.
    $sql1 = "SELECT * FROM ". TBL_LINKS. ", ". TBL_CATEGORIES ."
             WHERE lnk_cat_id = cat_id
             AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
             AND cat_type = 'LNK'
             ORDER BY cat_sequence, lnk_name, lnk_timestamp DESC
             LIMIT ". $_GET['start']. ", ". $linksPerPage;
}

$links_result = $g_db->query($sql1);

// Gucken wieviele Linkdatensaetze insgesamt fuer die Gruppierung vorliegen...
// Das wird naemlich noch fuer die Seitenanzeige benoetigt...
if ($g_valid_login == false)
{
    // Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
    $sql = "SELECT COUNT(*) FROM ". TBL_LINKS. ", ". TBL_CATEGORIES ."
            WHERE lnk_cat_id = cat_id
            AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
            AND cat_type = 'LNK' 
            AND cat_hidden = 0
            ORDER BY cat_sequence, lnk_name DESC";    
} 
else
{   
    // Alle Kategorien anzeigen
    $sql = "SELECT COUNT(*) FROM ". TBL_LINKS. ", ". TBL_CATEGORIES ."
            WHERE lnk_cat_id = cat_id
            AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
            AND cat_type = 'LNK'
            ORDER BY cat_sequence, lnk_name DESC";
}

$result = $g_db->query($sql);
$row = $g_db->fetch_array($result);
$numLinks = $row[0];

// Icon-Links und Navigation anzeigen

if ($_GET['id'] == 0 && ($g_current_user->editWeblinksRight() || $g_preferences['enable_rss'] == true))
{
    // Neuen Link anlegen
    if ($g_current_user->editWeblinksRight())
    {
        echo "
        <ul class=\"iconTextLinkList\">
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/links/links_new.php?headline=". $_GET["headline"]. "\"><img
                    src=\"". THEME_PATH. "/icons/add.png\" alt=\"Neu anlegen\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/links/links_new.php?headline=". $_GET["headline"]. "\">Neu anlegen</a>
                </span>
            </li>
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=LNK\"><img
                    src=\"". THEME_PATH. "/icons/application_double.png\" alt=\"Kategorien pflegen\" /></a>
                    <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=LNK\">Kategorien pflegen</a>
                </span>
            </li>
        </ul>";
    }

    // Navigation mit Vor- und Zurueck-Buttons
    $baseUrl = "$g_root_path/adm_program/modules/links/links.php?headline=". $_GET["headline"];
    echo generatePagination($baseUrl, $numLinks, $linksPerPage, $_GET["start"], TRUE);
}

if ($g_db->num_rows($links_result) == 0)
{
    // Keine Links gefunden
    if ($_GET['id'] > 0)
    {
        echo "<p>Der angeforderte Eintrag exisitiert nicht (mehr) in der Datenbank.</p>";
    }
    else
    {
        echo "<p>Es sind keine Einträge vorhanden.</p>";
    }
}
else
{

    // Zaehlervariable fuer Anzahl von fetch_object
    $j = 0;
    // Zaehlervariable fuer Anzahl der Links in einer Kategorie
    $i = 0;
    // Ueberhaupt etwas geschrieben? -> Wichtig, wenn es nur versteckte Kategorien gibt.
    $did_write_something = false;
    // Vorherige Kategorie-ID.
    $previous_cat_id = -1;
    // Kommt jetzt eine neue Kategorie?
    $new_category = true;
    // Schreibe diese Kategorie nicht! Sie ist versteckt und der User nicht eingeloggt
    $dont_write = false;

    // Solange die vorherige Kategorie-ID sich nicht veraendert...
    // Sonst in die neue Kategorie springen
    while (($row = $g_db->fetch_object($links_result)) && ($j<$linksPerPage))
    {

        if ($row->lnk_cat_id != $previous_cat_id)
        {
            if (($row->cat_hidden == 1) && ($g_valid_login == false))
            {
                // Nichts anzeigen, weil Kategorie versteckt ist und User nicht eingeloggt
                $dont_write = true;
            } else {
                $dont_write = false;
            }

            if (!$dont_write)
            {
                $i = 0;
                $new_category = true;
                $did_write_something = true;
                if ($j>0)
                {
                    echo "</div></div><br />";
                }
                echo "<div class=\"formLayout\">
                    <div class=\"formHead\">$row->cat_name</div>
                    <div class=\"formBody\" style=\"overflow: hidden;\">";
            }
        }

        if (!$dont_write)
        {
            if($i > 0)
            {
                echo "<hr />";
            }
            echo "
                <a class=\"iconLink\" href=\"$row->lnk_url\" target=\"_blank\"><img src=\"". THEME_PATH. "/icons/globe.png\"
                    alt=\"Gehe zu $row->lnk_name\" title=\"Gehe zu $row->lnk_name\" /></a>
                <a href=\"$row->lnk_url\" target=\"_blank\">$row->lnk_name</a>

                <div style=\"margin-top: 10px;\">";

                    // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
                    if ($g_preferences['enable_bbcode'] == 1)
                    {
                        echo $bbcode->parse($row->lnk_description);
                    }
                    else
                    {
                        echo nl2br($row->lnk_description);
                    }
                echo "</div>";

                if($g_current_user->editWeblinksRight())
                {
                    echo "
                    <div class=\"editInformation\">";
                        // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                        if ($g_current_user->editWeblinksRight())
                        {
                            echo "
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/links/links_new.php?lnk_id=$row->lnk_id&amp;headline=". $_GET['headline']. "\"><img 
                                src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\" /></a>
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/links/links_function.php?lnk_id=$row->lnk_id&amp;mode=4\"><img 
                                src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Löschen\" title=\"Löschen\" /></a>";
                        }
                        $user_create = new User($g_db, $row->lnk_usr_id);
                        echo "Angelegt von ". $user_create->getValue("Vorname"). " ". $user_create->getValue("Nachname").
                        " am ". mysqldatetime("d.m.y h:i", $row->lnk_timestamp);

                        if($row->lnk_usr_id_change > 0)
                        {
                            $user_change = new User($g_db, $row->lnk_usr_id_change);
                            echo "<br />Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname").
                            " am ". mysqldatetime("d.m.y h:i", $row->lnk_last_change);
                        }
                    echo "</div>";
                }

            $j++;
         }  // Ende Wenn !dont_write

         $i++;

         // Jetzt wird die jtzige die vorherige Kategorie
         $previous_cat_id = $row->lnk_cat_id;

         $new_category = false;
    }  // Ende While-Schleife

    // Es wurde noch gar nichts geschrieben ODER ein einzelner Link ist versteckt
    if (!$did_write_something)
    {
        echo "<p>Es sind keine Einträge vorhanden.</p>";
    }

    echo "</div></div>";
} // Ende Wenn mehr als 0 Datensaetze

echo '</div>';

if ($g_db->num_rows($links_result) > 2)
{
    // Navigation mit Vor- und Zurueck-Buttons
    // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
    $baseUrl = "$g_root_path/adm_program/modules/links/links.php?headline=". $_GET["headline"];
    echo generatePagination($baseUrl, $numLinks, $linksPerPage, $_GET["start"], TRUE);
}

require(THEME_SERVER_PATH. "/overall_footer.php");

?>
