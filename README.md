# Getaggte Foto-Sammlung für Druck aufbereiten

Diese Repo sammelt das Tooling um..
- Lokale Fotosammlung per Mac Finder taggen.
- Diese rekursiv heraussuchen, und umkopieren.
- Dort den Originalpfad und Dateiname per ImageMagick ins Bild bringen.
- Final dann Fotos online drucken lassen.
  - Anzahl ermitteln mit `find ~/Pictures/Export/fotosammlung -type f|wc -l `)
  - Gesamtgröße `du -hs ~/Pictures/Export/fotosammlung/*`

## TL;DR

**Bilder im Finder taggen** -> per Automator Program + Default für JPG-Dateien -> Cmd+O taggt Datei somit.

**Bilder zusammensuchen & watermarken**, Aufruf:

    php copy_photos.php -s ~/Pictures/fotos-archiv-test -d ~/Pictures/Export/fotosammlung

**Bilder drucken**  -> Rossmann
mindestmaße bei 300dpi und 10x15 -> 15/2,54*300 = 1770


## Fotos taggen

**per Finder**

manuell per Finder.. ist direkt als Attrib im Dateisystem verankert

blau = "Fotosammlung"

**per Lightroom**

trotz aktivierter XMP-Speicherung wird beim Farb-Taggen keine XMP geschrieben = kann nicht gelesen werden


## Fotos per Tag finden

https://github.com/jdberry/tag

`brew install tag`

`tag -f Fotosammlung`
-> Dateiliste zeilenweise!

### Weitere Infos:
- http://stackoverflow.com/questions/19720376/how-can-i-add-os-x-tags-to-files-programmatically


## Per ImageMagick ins Bild schreiben

- Seitenverhältnis beachten! immer unten schreiben
- weisse Schrift mit schwarzen Schemenrand
- verschiedene (lesbare!) Schriftarten

[Generating text with glow and stroke - PHP full example](http://www.imagemagick.org/discourse-server/viewtopic.php?t=9758)

[ImageMagick: Annotate - Text Drawing Operator](http://www.imagemagick.org/Usage/text/#annotate) (annotate = kommentiert)

[Mac-style font smoothing](http://www.imagemagick.org/discourse-server/viewtopic.php?f=1&t=17514)
zweimal Schrift mit leichten Versatz nach unten

### Experimente

#### spielen mit `-annotate`

`-annotate` ist einfacher zu bedienen als `-draw`

blau auf hellblau

    convert -size 320x100 xc:lightblue -font Candice -pointsize 72 -fill blue  -annotate +25+70 'Anthony' annotate.gif

schwarz mit gelben Rand auf transparent

    convert -size 1024x100 xc:none -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 72 -fill black -stroke yellow -annotate +25+70 '2001/pfad/zum/bild.jpg' annotate.png

per label  - geht schon, ist aber nicht transparent

    convert -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 36  label:'2001/pfad/zum/bild.jpg' +append label.png

#### spielen mit `composite`

[IM Usages Compose](http://www.imagemagick.org/Usage/compose/)

[Image Positioning using Gravity](http://www.imagemagick.org/Usage/annotating/#gravity)

Bilder stehen nebeneinander

    composite -gravity center schatten.png schrift.png beides.png

Schattenbild füllt Konturen von Schrift aus - interessant, ist aber noch nicht das Zielbild

    composite -compose atop schatten.png schrift.png beides.png

Zusammensetzen in Richtig!

    convert bild.jpg \
        -gravity SouthWest -draw "image Over 15,10 0,0 'schatten.png'" \
        -gravity SouthWest -draw "image Over 10,20 0,0 'schrift.png'" \
        bild_fertig.jpg

#### Schatten direkt mit Text .. oh my

http://www.imagemagick.org/Usage/fonts/ -> "Denser Soft Outline"

    convert -size 1024x130 xc:none -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 72 \
               -stroke black -strokewidth 8 -annotate +25+75 '2001/pfad/zum/bild.jpg' -blur 0x8 \
               -fill white   -stroke none   -annotate +25+75 '2001/pfad/zum/bild.jpg' \
               beides.png

#### als Maske in Bild einpassen

http://www.imagemagick.org/Usage/fonts/#mask

    convert -size 1024x100 xc:transparent -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 50 \
               -fill black        -annotate +24+64 '2001/pfad/zum/bild.jpg' \
               -fill white        -annotate +26+66 '2001/pfad/zum/bild.jpg' \
               -fill transparent  -annotate +25+65 '2001/pfad/zum/bild.jpg' \
               trans_stamp.png

    convert -size 1024x100 xc:black -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 50 \
               -fill white   -annotate +24+64 '2001/pfad/zum/bild.jpg' \
               -fill white   -annotate +26+66 '2001/pfad/zum/bild.jpg' \
               -fill black   -annotate +25+65 '2001/pfad/zum/bild.jpg' \
               mask_mask.jpg

    composite trans_stamp.png   bild.jpg   mask_mask.jpg \
              bild_fertig.jpg

statt 3 Bilder besser mit Zwischenbild:

    composite -compose CopyOpacity mask_mask.jpg trans_stamp.png trans_stamp3.png

    composite trans_stamp3.png bild.jpg  bild_fertig.jpg

ist noch oben links, muss runter..

    convert bild.jpg \
        -gravity SouthWest -draw "image Over -10,-10 0,0 'trans_stamp3.png'" \
        bild_fertig.jpg



## finale Schritte prototypisch

### Variante 1: Text mit Schatten

#### Schritt 1: Text mit Schatten erzeugen

    convert -size 1024x120 xc:none -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 50 \
           -stroke black -strokewidth 8 -annotate +25+80 '2001/pfad/zum/bild.jpg' -blur 0x8 \
           -fill lightgray   -stroke none   -annotate +25+80 '2001/pfad/zum/bild.jpg' \
           beides.png

#### Schritt 2: Text und Schatten verbinden und in Bild einfügen

Normales Bild

    convert bild.jpg \
        -gravity SouthWest -draw "image Over -10,-10 0,0 'beides.png'" \
        bild_fertig.jpg

Hochkant Bild

    convert bild.jpg -rotate -90 \
          -gravity SouthWest -draw "image Over 0,-10 0,0 'beides.png'" \
          -rotate 90  bild_fertig.jpg

Problem: beides.png ist so breit/hoch wie das Bild, ist also nicht unten links platzierbar.


### Variante 2: Text maskiert und eingestempelt in Bild

#### Schritt 1: Text-Maske erzeugen

transparentes Bild erzeugen

    convert -size 1024x100 xc:transparent -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 50 \
               -fill black        -annotate +24+64 '2001/pfad/zum/bild.jpg' \
               -fill white        -annotate +26+66 '2001/pfad/zum/bild.jpg' \
               -fill transparent  -annotate +25+65 '2001/pfad/zum/bild.jpg' \
               trans_stamp.png

gleiches nochmal mit schwarzen Hintergrund

    convert -size 1024x100 xc:black -font ../fonts/YanoneKaffeesatz-Regular.ttf -pointsize 50 \
               -fill white   -annotate +24+64 '2001/pfad/zum/bild.jpg' \
               -fill white   -annotate +26+66 '2001/pfad/zum/bild.jpg' \
               -fill black   -annotate +25+65 '2001/pfad/zum/bild.jpg' \
               mask_mask.jpg

Deckkraft ermitteln und als PNG speichern - die finale Maske

    composite -compose Copy Opacity mask_mask.jpg trans_stamp.png text_mask.png

    rm trans_stamp.png mask_mask.jpg


#### Schritt 2: Text in Bild einfügen

Normales Bild

    convert bild.jpg \
        -gravity SouthWest -draw "image Over -10,-10 0,0 'text_mask.png'" \
        bild_fertig.jpg

Hochkant Bild

    convert bild.jpg -rotate -90 \
          -gravity SouthWest -draw "image Over 0,-10 0,0 'text_mask.png'" \
          -rotate 90  bild_fertig.jpg

    rm text_mask.png


## Finder: Fotos einfach taggen

Feststellung: es ist unglaublich kompliziert, per Tastatur einfach mal eine Datei zu vertaggen :(

### Lösung 1: Automator Programm

Per Automator ein Programm erstellen, das Finder Eingabe verwendet und ein Script ausführt. Einfach mal ein sh-Script als Programm festlegen geht leider nicht.
[How to execute a shell script with selected files/folders in finder?](http://superuser.com/questions/154726/how-to-execute-a-shell-script-with-selected-files-folders-in-finder)
Wenn man temporär für \*.jpg dieses Programm zuordnet, kann man per Doppelklick den Tag festlegen. Fast gut genug. Fehlt noch per ?+Enter starten für volle Tastatursteuerung. Ah, [Cmd+O oder Cmd+Down](https://discussions.apple.com/thread/1682754)

Meldet Fehler beim ausführen im Finder.
Testen via Commandline: [Pass arguments to Automator or AppleScript workflows from the command line](https://jamietshaw.wordpress.com/2011/11/13/pass-arguments-to-automator-or-applescript-workflows-from-the-command-line/)

Testen mit Task "Angegebene Finder-Objekte abfragen". Dann testen in Automator meldet tatsächlich Fehler. Pfad zu `tag` musste mit angegeben werden. (`/usr/local/bin/tag`).


### Lösung 2: Automator Service

Per Automator einen Service erstellen, der den Tag setzt. Diesen dann per Shortcut ansprechen.
[Finder Label or Tag Keyboard Shortcut](https://discussions.apple.com/thread/5798486?start=0&tstart=0)
Damit erscheint das Zuordnen im Kontextmenü von Dateien und ist per Shortcut aufrufbar. Für Massen-Vertaggung aber nicht gut geeignet.


## Bild-Unterschrift

### UTF8-Probleme mit ImageMagick und Annotate

http://www.imagemagick.org/Usage/text/#unicode
http://www.wizards-toolkit.org/discourse-server/viewtopic.php?t=12711

Test mit "Käsefodue" wird zu "K?ase"

    php copy_photos.php -s ~/Pictures/fotos-thumbs/2013 -d ~/Pictures/Export/fotosammlung/2013 -r -t Rot

### Text: EXIF-Daten nutzen für exakten Zeitpunkt

per `exif_read_data` das `DateTimeOriginal auslesen

Titelprefix per `-p`

Formatvorschrift:

    Prefix: Ordnernamen ohne Datum / Dateiname (Datum aus EXIF)

Wenn `DateTimeOriginal` falsch ist bzgl. Jahresangabe in `-p` dann wird ein Timestamp im Pfad gesucht und verwendet.

### Text: Cleanup

- alle Timestamps entfernen
- "/# " zu ""
- "/lightroom/" entfernen

### Text-Größe anpassen anhand von Bildgröße

Was wenn Text zu lang? z.B. "Urlaub/2011-05-21 Toskana/02_23-05 Montag - Baden am Meer/01 - unser Stranddomizil am Naturstrand.JPG", "Urlaub_2011-05-21_Toskana_03_24-05_Dienstag_-\_Lari_05 - wir gemeinsam.jpg"

- Bilder mit 1024px Breite? dann Text kleiner
- 2-3 Varianten Textgröße
- wenn viel Text, dann auch kleiner


## Tags

### Tags eines Verz. speichern

Speichert alle Tags an Dateien in einen Verzeichnis in einer JSON.

    php save_tags.php -d ~/Pictures/fotos-thumbs -j ~/Pictures/fotos-thumbs/tagged_fotosammlung.json -t Fotosammlung

### Tags eines Verz. wiederherstellen

Alle Dateien einer JSON werden wiederhergestellt.

    php restore_tags.php -j /Volumes/Daten/ronny/Pictures/fotos-thumbs/tagged_fotosammlung.json -t Fotosammlung -d /Volumes/Daten/ronny/Pictures/fotos-archiv

    php restore_tags.php -j ~/Pictures/fotos-thumbs/2005/Suedafrika_Foto-DVD/tags.json -d ~/Pictures/fotos-archiv/2005/Suedafrika_Foto-DVD  -t Fotosammlung

Geht auch Jahr-weise - siehe `restore_tags.sh`.


## Bilder mit Überbreite erkennen

Da der Fotodrucker Bilder mit Überbreite trotzdem druckt, muss ich diese mit der Schere beschneiden, damit es auf **15cm Breite** in die Fotobox passt.
Das nächste Mal wäre eine Erkennung gut. Dazu folgendes Script:

    php detect_wide_pics.php -d ~/Pictures/fotos-thumbs -f 1.51 -t Fotosammlung -j detect_wide_pics.json

## Suchen nach nicht aktualisierten Bildern (älter als 1 Tag)

    find ~/Pictures/Export/fotosammlung -type f -mtime +1
