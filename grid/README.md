Herbie Grid Plugin
==================

`Grid` ist ein [Herbie](http://github.com/getherbie/herbie) Plugin, mit dem du in deinen Inhalten (HTML-/CSS-)Grids 
erstellen kannst.


## Installation

Das Plugin installierst du via Composer.

	$ composer require getherbie/plugin-grid

Danach aktivierst du das Plugin in der Konfigurationsdatei.

    plugins:
        enable:
            - grid

## Konfiguration

In der Konfigurationsdatei definierst du die gewünschten Vorlagen.

    plugins:
        config:
            grid:
                templates:
                    3col:
                        row: '<div class="pure-g">{row}</div>'
                        cols:
                            - '<div class="pure-u-1-3 col-{index}" markdown="1">{col}</div>'
                            - '<div class="pure-u-1-3 col-{index}" markdown="1">{col}</div>'
                            - '<div class="pure-u-1-3 col-{index}" markdown="1">{col}</div>'
                    2col:
                        row: '<div class="pure-g">{row}</div>'
                        cols:
                            - '<div class="pure-u-1-2 col-{index}" markdown="1">{col}</div>'
                            - '<div class="pure-u-1-2 col-{index}" markdown="1">{col}</div>'
                            
Falls du innerhalb eines Grids Markdown verwenden willst, muss das HTML-Attribut `markdown="1"` gesetzt sein.
                             
## Anwendung

Nun kannst du in deinen Seiten die konfigurierten Grid-Templates aufrufen.

    -- grid 3col --
    Inhalt Spalte 1 
    --
    Inhalt Spalte 2 
    --
    Inhalt Spalte 3 
    -- grid --
    
    Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod 
    tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.
    
    -- grid 2col --
    Inhalt Spalte 1 
    --
    Inhalt Spalte 2 
    -- grid --

## Demo

<http://www.getherbie.org/dokumentation/plugins/grid>
