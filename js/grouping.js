
var maxwidth = 85;  //maximum width of a tudentbox in px. initialised as the minimum possible width.

//circles are identified by their index in these arrays
var circleAssignments = [];
var circleNames = [];
var circleGroupIds = [];
var selectedStudents = [];
var circleAdminIds = [];
var circleAdminNames = [];

var preventStudentClick = 0;

var circles = new Array();

/**
 * Function that synchronize values from html-view to js-model
 * using global var circles
 */
function synchroniseViewToModel() {
    circles = new Array();
    $("#circles > .circle").each(function() {
        var members = new Array();
        $(this).find(".circleUser").each(function() {
            members.push(parseInt($(this).attr("userid")));
        });
        circles.push({ 'id': $(this).attr('id'),
                       'groupid': $(this).attr('groupid'),
                       'name': $(this).find('.circle__name').text(),
                       'admin': { 'id': $(this).find('.circle__admin').attr('adminId'),
                                 'name': $(this).find('.circle__admin').text() },
                       'members': members });
    });
}

/**
 * Function that synchronize values from js-model to html-view
 * using global var circles
 */
function synchroniseModelToView() {
    $("#circles > .circle").each(function() { $(this).remove(); });
    for (var i in circles) {
        createCircle(circles[i]).appendTo('#circles');
        refreshMemberPositions(circles[i].id);
    }
    $('#stepper').text(circles.length);
}

/**
 * Function of init
 */
$(function() {
    $('.user').draggable({appendTo: 'body', helper: 'clone'});
    
    // add select role function
    $("#roleselect").change(function() {
        update_participant_views();
    });

    $("#groupselect").change(function() {
        update_participant_views();
    });

    var numGroups = parseInt($("#stepper").text());
 
    $("#addgroup").click(function() {
       var x = parseInt($('#stepper').text()) + 1;
       updateCircles(x);
    });
    
    $(".user").each(function() { if($(this).width() > maxwidth) maxwidth = $(this).width(); });
    $(".user").each(function() { $(this).width(maxwidth); });
    $("#participants").droppable({
        accept: function(element) {
            if (element.hasClass('circle')) {
                return true;
            } else if (element.hasClass('image') && element.hasClass('circleUser')) {
                return true;
            }
            return false;
        },
        drop: function(event, ui) {
            if (ui.draggable.hasClass('circle')) {
                ui.draggable.remove();
                var x = parseInt($('#stepper').text()) - 1;
                updateCircles(x);
            } else if (ui.draggable.hasClass('image') && ui.draggable.hasClass('circleUser')) {
                var circleFromId = ui.draggable.attr('circleid');
                var count = parseInt($('#' + circleFromId).find('.circle__number').text());
                $('#' + circleFromId).find('.circle__number').text(count - 1);
                ui.draggable.remove();
                refreshMemberPositions(circleFromId);
            }
        }
    });
    synchroniseViewToModel();
    synchroniseModelToView();
});

function randomiseArray(inArray) {
	//much more random than sort()
	var ret = [];
	var array = inArray.slice(0);
	for(i = array.length; i > 0; i--) {
		index = Math.floor(Math.random()*i);
		ret.push(array[index]);
		array.splice(index,1);
	}
	return ret;
}

function refreshMemberPositions(circleId) {
    //-- circle margins for imgs
    var marginLeft = ['-15px','18.5px','43px','52px','43px','18.5px','-15px','-48.5px','-73px','-82px','-73px','-48.5px'];
    var marginTop  = ['-82px','-73px','-48.5px','-15px','18.5px','43px','52px','43px','18.5px','-15px','-48.5px','-73px'];
    $('#' + circleId + ' > img[circleid="' + circleId + '"] ').each(function(index) {
        $(this).css('left', null).css('top', null);
        $(this).css('margin-left', marginLeft[index % 12]);
        $(this).css('margin-top', marginTop[index % 12]);
    });
}

function createMemberCircle(circleId, userId) {
    var memberCircle = $('#user-' + userId).find('.userList__person').clone();
    memberCircle.removeClass('userList__person').addClass('image').addClass('circleUser').addClass('canRemove');
    if ($('#user-' + userId).attr('id') == undefined) {
        var memberCircle = $('<img src="js/none.png" class="userList__person defaultuserpic" width="48" height="48">');
        memberCircle.easyTooltip({content: 'userid :' + userId });
    } else {
        memberCircle.easyTooltip({content: $('#user-' + userId).find('.userList__name').html()});
    }
    memberCircle.css('position', 'absolute');
    memberCircle.attr('userid', userId);
    memberCircle.attr('circleid', circleId);
    //-- append to circle
    memberCircle.draggable({revert: 'invalid',
        start: function(event, ui) {
            $('#easyTooltip').remove();
            var circle = $('#' + ui.helper.attr('circleid'));
            circle.removeClass('circle_over');
        }
    });
    memberCircle.dblclick(function() {
        var circleId = $(this).attr('circleid');
        var count = parseInt($('#' + circleId).find('.circle__number').text());
        $('#' + circleId).find('.circle__number').text(count - 1);
        $(this).remove();
        refreshMemberPositions(circleId);
    });
    return memberCircle;
}

function createCircle(circle) {
    var circleDiv = $('<div class="circle circle_full" id="' + circle.id + '" groupid="' + circle.groupid + '">' +
                    '<div class="circle__disk"></div>' +
                    '<div class="circle__inner">' +
                    '<div class="circle__name">' + circle.name +'</div>' +
                    '<div class="circle__admin" adminId="' + circle.admin.id + '" style="display: none;">'+ circle.admin.name + '</div>' +
                    '<div class="circle__number">' + circle.members.length + '</div>' +
                    '<div class="circle__remove">remove</div>' +
                    '</div>' +
                    '</div>');
    // add members
    for (var i in circle.members) {
        createMemberCircle(circle.id, circle.members[i]).appendTo(circleDiv);
    }

    // add events to group circle div
    circleDiv.find('.circle__inner').find('.circle__name').easyTooltip({content: 'Create By ' + circle.admin.name});
    circleDiv.find('.circle__inner').find('.circle__name').dblclick(function() {
        $('#easyTooltip').remove();
        var circleTextBox = $('<input type="text" value="' + $(this).text() + '" />');
        circleTextBox.css('border-width','0px');
        
        function textBoxDone() {
            $(this).text(circleTextBox.val());
            circleTextBox.remove();
        }
        
        //conditionally attach the textBoxDone event if you click outside the textbox
        var mdevent = function(evt) {
            if(evt.target!=circleTextBox.get(0)) {
               circleTextBox.parent().text(circleTextBox.val());
               circleTextBox.remove();
               $(document).unbind('mousedown', mdevent);
            }
        }
        $(document).mousedown(mdevent);
        //if you press return
        circleTextBox.keypress(function(evt) {
            if(evt.keyCode==13) { // key return 
                $(this).parent().text($(this).val())
                $(this).remove();
                $(document).unbind('mousedown', mdevent);
            }
        });
        $(this).text('');
        $(this).append(circleTextBox);
        circleTextBox.focus();
        circleTextBox.select();
    });
    //-- add members droppable
    circleDiv.droppable({
        accept: function (element) {
            if (element.hasClass('user')) {
                var userId = /user-(\d+)/.exec(element.attr('id'))[1];
                if ($(this).find('img[userid="' + userId + '"]').length > 0 ) {
                    return false;
                }
                return true;
            } else if (element.hasClass('image') && element.hasClass('circleUser')) {
                if ($(this).attr('id') !=  element.attr('circleid')) {
                    var userId = element.attr('userid');
                    if ($(this).find('img[userid="' + userId + '"]').length > 0 ) {
                        return false;
                    }
                    return true;
                }
            }
            return false;
        },
        hoverClass: 'circle_over_bg',
        drop: function(event, ui) {
            if (ui.draggable.hasClass('user')) {
                var userId = /user-(\d+)/.exec(ui.draggable.attr('id'))[1];
                var count = parseInt($(this).find('.circle__number').text());
                $(this).find('.circle__number').text(count + 1);
                createMemberCircle($(this).attr('id'), userId).appendTo($(this));
            } else if (ui.draggable.hasClass('image') && ui.draggable.hasClass('circleUser')) {
                var circleFromId = ui.draggable.attr('circleid');
                var count = parseInt($(this).find('.circle__number').text());
                $(this).find('.circle__number').text(count + 1);
                var circleFrom = $('#' + ui.draggable.attr('circleid'));
                circleFrom.find('.circle__number').text(parseInt(circleFrom.find('.circle__number').text()) - 1);
                ui.draggable.attr('circleid', $(this).attr('id'));
                ui.draggable.appendTo($(this));
                refreshMemberPositions(circleFromId);
            }
            refreshMemberPositions($(this).attr('id'));
        }
    });
    //--remove circle event
    circleDiv.find('.circle__remove').dblclick(function() {
        circleDiv.remove();
        var x = parseInt($('#stepper').text()) - 1;
        updateCircles(x);
    });
    //-- add circles dropable
    circleDiv.draggable({revert: 'invalid', appendTo: 'body'});
    circleDiv.mouseover(function() {
        $(this).addClass('circle_over');
        $(this).find('.circle__remove').show();
    });
    circleDiv.mouseout(function() {
        $(this).removeClass('circle_over');
        $(this).find('.circle__remove').hide();
    });
    return circleDiv;
}

function updateCircles(num) {
    synchroniseViewToModel();
    //--remove circles
    if (circles.length > num) {
        for (var i = num; i < circles.length; i++) {
            $('#' + circles[i].id).remove();
        }
        circles = circles.slice(0, num);
    }
    
    //--update value of id in circle and members
    for (var i in circles) {
        if (circles[i].id != 'circle-' + i) {
            var circleId = 'circle-' + i;
            var circleDiv = $('#' + circles[i].id);
            circleDiv.attr('id', circleId);
            circleDiv.find('.circleUser').each(function() {
                $(this).attr('circleid', circleId);
            });
            circles[i].id = circleId;
        }
    }
    
    //--add new circles
    if (circles.length < num) {
        for(i = circles.length; i < num; i++) {
            circle = { 'id' : 'circle-' + i,
                           'groupid' : 0,
                           'name' : 'Team ' + (i+1),
                           'admin' : {'id' : 0, 'name' : ''},
                           'members' : new Array() };
            circles.push(circle);
            createCircle(circle).appendTo('#circles');
        }
    }
    
    //--set display value
    $('#stepper').text(num);
}

function resetGroupMembers() {
    synchroniseViewToModel();
    for(var i in circles) {
        circles[i].members = new Array();
    }
    synchroniseModelToView();
}

function assignRandomly() {
    synchroniseViewToModel();
    var unassigneds = new Array();
    $("#participants .user:visible").each(function() {
        rslt = /user-(\d+)/.exec(this.id);
        var existInAssign = false;
        var userId = parseInt(rslt[1]);
        for (var i in circles) {
            for (var j in circles[i].members) {
                if (userId == circles[i].members[j]) {
                    existInAssign = true;
                }
                if (existInAssign) { break; }
            }
            if (existInAssign) { break; }
        }
        if (!existInAssign) { unassigneds.push(userId); }
    });
    unassigneds = randomiseArray(unassigneds);
    while (unassigneds.length > 0) {
        //get the circle(s) with the lowest numbers
        var lowestCircle = 0;
        var lowestCircles = new Array();
        for (var i in circles) {
            c = circles[i].members;
            lc = circles[lowestCircle].members;
            if (c.length < lc.length) {
                lowestCircle = i;
                lowestCircles = new Array();
            } else if (c.length == lc.length) {
                lowestCircles.push(i);
            }
        }
        lowestCircles.push(lowestCircle);
        //pick a random circle from the list of lowest circles
        do {
            var randomCircle = Math.floor(Math.random() * lowestCircles.length);
        } while (randomCircle >= lowestCircles.length); //on the OFF CHANCE that Math.random() produces 1
        circles[lowestCircles[randomCircle]].members.push(unassigneds.pop());
    }
    synchroniseModelToView();
}

function update_participant_views() {
    var selector = "";
    $("#participants .user").hide();
    if ($("#roleselect").val()!=0) selector += "[roles*='"+$("#roleselect").val()+",']";
    if ($("#groupselect").val()!=0) selector += "[groups*='"+$("#groupselect").val()+",']";
    $("#participants .user"+selector).show();
    if ($("#groupselect").val()==0 && $("#roleselect").val()==0) $("#participants .user").show();
}

function getUrlVars() {
    var vars = [], hash;
    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
    for(var i = 0; i < hashes.length; i++) {
        hash = hashes[i].split('=');
        vars.push(hash[0]);
        vars[hash[0]] = hash[1];
    }
    return vars;
}

/**
 * function to generate form and submit group's data of grouping
 **/
function saveGrouping() {
    if ($('#groupingname').val().trim().length == 0) {
        alert('O nome de agrupamento não pode ser vazio');
        return 'error';
    }
    synchroniseViewToModel();
    
    var params = getUrlVars();
    var action = 'create';
    var htmlParams = 'course='+ params['course'];
    if (params['grouping'] != null && params['grouping'] != '') {
        htmlParams += '&grouping=' + params['grouping'];
        action = 'update';
    } else if ($('#isupdate').val() != null) {
        htmlParams += '&grouping=' + $('#isupdate').val();
        action = 'update';
    }
    var form = $('<form action="?' + htmlParams + '" method="POST"></form>');
	var input = $('<input type="hidden" name="grouping_form" value="is_submitted" />');
    form.append(input);
    // build for circles
    for (var i in circles) {
        var input = $('<input type="hidden" name="circles[' + i + '][name]" value="' + circles[i].name + '" />');
        form.append(input);
        var input = $('<input type="hidden" name="circles[' + i + '][groupid]" value="' + circles[i].groupid + '" />');
        form.append(input);
		for (var j in circles[i].members) {
			var input = $('<input type="hidden" name="circles[' + i + '][members][' + j +']" value="' + circles[i].members[j] + '" />');
			form.append(input);
		}
	}
    var action = $('<input type="hidden" name="action" value="' + action + '" />');
	var name = $('<input type="hidden" name="groupingname" value="' + $('#groupingname').val() + '" />');
    form.append(action);
    form.append(name);
    if ($('#inherit').attr("checked")) {
    	var inherit = $('<input type="hidden" name="inherit" value="' + $('#inherit').val() + '" />');
        form.append(inherit);
    }
    if ($('#addme').attr("checked")) {
    	var addme = $('<input type="hidden" name="addme" value="' + $('#addme').val() + '" />');
        form.append(addme);
    }
	form.submit();
}

