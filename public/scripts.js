var params = (new URL(location)).searchParams;
var show_all = parseInt (params.get ("show_all"));
if (isNaN (show_all))
  show_all = 0;

var all_optional = cfg.conf_key == "pace" ? 1 : 0;
//all_optional = 0;

function update_hides () {
  for (var idx in questions) {
    var q = questions[idx];
    var want_field;
    if (q.show_if) {
      want_field = false;
      var target_id = "i_" + q.show_if[0];
      var val = $("input[name='"+target_id+"']:checked").val();
      if (q.show_if.includes (val)) {
	want_field = true;
      }
    } else {
      want_field = true;
    }
    if (show_all)
      want_field = true;
    
    var section_id = "s_" + q.id;
    if (want_field) {
      $("#"+section_id).show();
    } else {
      $("#"+section_id).hide();
    }
  }
}


function is_required_question_empty (q) {
  if (all_optional)
    return (false);

  if (q.optional)
    return (false);
  
  var input_id = "i_"+q.id;
  if (q.choices) {
    let choice = $("input[name='"+input_id+"']:checked").val();
    if (choice != undefined && choice.trim() != "")
      return (false);
  } else {
    if ($("#"+input_id).val().trim() != "")
      return (false);
  }

  if (q.show_if) {
    var section_id = "s_"+q.id;
    if ($("#"+section_id).is(":hidden"))
      return (false);
  }

  return (true);
}

function apply_submit () {
  for (var idx in questions) {
    var q = questions[idx];
    if (is_required_question_empty (q)) {
      var section_id = "s_" + q.id;
      console.log (section_id);
      var section = $("#"+section_id);
      $(section).find(".required_text").html("required");
      $(window).scrollTop ($(section).offset().top);
      return (false); /* kill submit */
    }
  }
  return (true); /* ok for submit to go through */
}
/*

  $(elt).find(".current_members").remove();
  let txt = "<div class='current_members'>\n";
  txt += "Current members: ";
  txt += "</div>\n";
  $(elt).append (txt);
*/

function do_lookup_change (ev) {
  let input_elt = $(ev.target);
  let val = $(input_elt).val().trim();

  let input_wrapper = $(input_elt).parents(".input_wrapper");
  let span = $(input_elt).parents("span");

  /* for removing extra busy_people */
  $(input_wrapper).find(".del_button").show();

  if (val == "") {
    $(input_wrapper).find(".group_members").remove();
    $(span).find(".lookup_success_msg").remove();
    $(span).find(".lookup_fail_msg").remove();
  } else {
    $.getJSON ("lookup_check.php",
	       { name: val },
	       (ret) => {
		 console.log (ret);
		 $(input_wrapper).find(".group_members").remove();
		 $(span).find(".lookup_success_msg").remove();
		 $(span).find(".lookup_fail_msg").remove();
		 
		 if (typeof (ret) != "object")
		   ret = {};
		 let txt = "";
		 if (ret.id) {
		   txt += "<span class='lookup_success_msg'>";
		   txt += "Good match in master database!";
		   txt += "</span>";
		 } else {
		   txt += "<span class='lookup_fail_msg'>";
		   txt += "New name to create in master database.";
		   txt += "</span>";
		 }
		 $(span).append (txt);
		 
		 if (ret.group && ret.members) {
		   $(input_wrapper).remove (".group_members");
		   var div = document.createElement("div");
		   $(div).attr ("class", "group_members");
		   div.textContent = "Current members: "
		     + ret.members.join ("; ");
		   $(input_wrapper)[0].appendChild (div);
		 }
	       });
  }
  return (true);
}

function do_add_another (ev) {
  let elt = $(ev.target).parents(".question").find(".input_wrapper");
  $(elt).append("<div>\n"
		+"<span>\n"
		+"<input type='text' name='i_busy_people[]'"
		+"   class='lookup_individual'"
		+"   size='40' />\n"
		+"<button type='button' style='display:none' class='del_button'>"
		+"delete</button>\n"
		+"</span>\n"
		+"</div>");

  setup_lookups ();
}

function do_del_button_click (ev) {
  let elt = $(ev.target);
  $(elt).parents("span").remove();
}

function setup_lookups () {
  $(".lookup_individual").autocomplete({ source: "lookup_individual.php" });
  $(".lookup_individual").attr("autocomplete","correspondent-name");
  $(".lookup_individual").off ("change.neffa");
  $(".lookup_individual").on ("change.neffa", do_lookup_change);

  $(".lookup_group").autocomplete({ source: "lookup_group.php" });
  $(".lookup_group").attr("autocomplete","correspondent-name");
  $(".lookup_group").off ("change.neffa");
  $(".lookup_group").on ("change.neffa", do_lookup_change);

  $(".del_button").off ("click.neffa");
  $(".del_button").on ("click.neffa", do_del_button_click);
}

function do_sched_any (ev) {
  var elt = ev.target;
  checked = elt.checked;
  $(".sched_item").prop ("checked", checked);
  $(".sched_all_day").prop ("checked", checked);
}

function do_all_day (ev) {
  var elt = ev.target;
  var checked = elt.checked;
  
  var day = $(elt).data("day");

  $(".sched_item").filter (function () { 
    return ($(this).attr ("data-day") == day) 
  }).prop ("checked", checked);

  $("#sched_any").prop ("checked", false);
}

function do_sched_item (ev) {
  var elt = ev.target;
  var checked = elt.checked;
  
  if (! checked) {
    $("#sched_any").prop ("checked", false);
    $(".sched_all_day").prop ("checked", false);
  }
}

$(function () {
  $("input[type='radio']").change (update_hides);
  $("#apply_form").submit (apply_submit);

  $("#sched_any").change (do_sched_any);
  $(".sched_all_day").change (do_all_day);
  $(".sched_all_day").change (do_all_day);
  $(".sched_item").change (do_sched_item);

  if (window.questions)
    update_hides ();

  setup_lookups ();
  
  $("#add_another").click(do_add_another);

  if (show_all)
    $(".debug").show();
});
