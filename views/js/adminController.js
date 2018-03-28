/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    mpSOFT <imfo@mpsoft.it>
*  @copyright 2017 mpSOFT Massimiliano Palermo
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/


$(document).ready(function(){
    let total = $('input[name="input_hidden_total_documents').val();
    $('#form-expdoc .panel-heading .badge').after('<span class="badge">' + total + '</span>');
});

function exportSelectedDocuments()
{
    var title = 'Confirm';
    var translate = 'Export selected documents?';
    var translation = '';
    $.ajax({
        type: 'POST',
        dataType: 'json',
        async: false,
        useDefaultXhrHeader: false,
        data: 
        {
            ajax: true,
            action: 'GetTranslation',
            translate: translate,
            title: title
        }
    })
    .done(function(json){
        if (json.result === true) {
            translation = json.translation;
            title = json.title;
        } else {
            translation = translate; 
        }
    })
    .fail(function(){
        translation = translate;
    });
    
    jConfirm(translation, title, function(r){
        if (r===true) {
            var boxes = getSelectedBoxes();
            $.ajax({
                type: 'POST',
                dataType: 'json',
                useDefaultXhrHeader: false,
                data: 
                {
                    ajax: true,
                    action: 'ExportSelected',
                    list_of_ids: boxes,
                    type: $('#input_select_type_document').val()
                }
            })
            .done(function(json){
                createXML(json.content);
            })
            .fail(function(){
                jAlert('AJAX ERROR');
            });
        }
    });
}
    
function createXML(content)
{
    var xmltext = content;
    //var pom = document.createElement('a');

    var filename = "export_documents_" +  formatDate(Date.now()) + ".xml";
    var pom = document.createElement('a');
    var bb = new Blob([xmltext], {type: 'text/plain'});

    pom.setAttribute('href', window.URL.createObjectURL(bb));
    pom.setAttribute('download', filename);

    pom.dataset.downloadurl = ['text/plain', pom.download, pom.href].join(':');
    pom.draggable = true; 
    pom.classList.add('dragout');

    pom.click();
}
    
function formatDate(date) 
{
    var d = new Date(date),
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear(),
        hour = d.getHours(),
        minutes = d.getMinutes(),
        seconds = d.getSeconds();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;
    if (hour.length <2) hour = '0' + hour;
    if (minutes.length <2) minutes = '0' + minutes;
    if (seconds.length <2) seconds = '0' + seconds;

    return [year, month, day, hour, minutes, seconds].join('');
}
    
function getSelectedBoxes()
{
    var output = [];
    $('input[name="chkBoxes[]"]:checked').each(function(){
        output.push(this.value);
    });
    return output;
}
    
function toggleAllBoxes(toggle)
{
    $('input[name="chkBoxes[]"]').each(function(){
        this.checked = toggle;
    });
}
