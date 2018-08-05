function show_if_test (condition) {
  var target_id = "i_" + condition[0];
  var val = $(document.getElementsByName (target_id))
      .filter ("input:checked")
      .val();
  return (condition.includes (val));
}

function update_sched_hides () {
  let val = $("input[name=i_app_category]:checked").val();
  if (val == "Performance") {
    $(".sched_ext").hide();
  } else {
    $(".sched_ext").show();
  }
}

function update_hides () {
  update_sched_hides ();

  let show_all = $("#show_all").is(":checked");

  for (var idx in questions) {
    var q = questions[idx];
    var want_field = true;

    if (q.show_if) {
      if (Array.isArray (q.show_if[0])) {
	q.show_if.forEach (function (condition) {
	  if (! show_if_test (condition))
	    want_field = false;
	});
      } else {
	if (! show_if_test (q.show_if))
	  want_field = false;
      }
    }

    if (show_all)
      want_field = true;
    
    var section_id = "s_" + q.id;
    var elt = document.getElementById ("s_"+q.id);
    if (want_field) {
      $(elt).show();
    } else {
      $(elt).hide();
    }
  }

  if (show_all)
    $(".debug").show();
  else
    $(".debug").hide();
}

function is_radio_complete (q) {
  let saw_blank = false;
  let section_id = "s_"+q.id;
  $(document.getElementById(section_id))
    .find("input[type=radio]")
    .each(function (idx, elt) {
      if (! $(elt).is(":hidden")) {
	let name = $(elt).attr("name");
	let val = $(document.getElementsByName (name))
	    .filter ("input:checked")
	    .val();
	if (val == undefined)
	  saw_blank = true;
      }
    });
  return (! saw_blank);
}

function is_choice_complete (q) {
  let input_id = "i_"+q.id;
  val = $(document.getElementsByName (input_id))
    .filter("input:checked")
    .val();
  if (val != undefined && val.trim() != "")
    return (true);

  return (false);
}

function valid_response (q) {
  if ($("#all_optional").is(":checked"))
    return (true);

  if (q.show_if) {
    var elt = document.getElementById ("s_"+q.id);
    if ($(elt).is(":hidden"))
      return (true);
  }

  if (q.optional)
    return (true);

  if (q.type == "radio")
    return (is_radio_complete (q));

  if (q.choices)
    return (is_choice_complete (q));

  let input_id = "i_"+q.id;
  let val;
  val = $(document.getElementById (input_id)).val();

  if (val != undefined && val.trim() != "")
    return (true);

  return (false);
}

function apply_submit () {
  for (var idx in questions) {
    var q = questions[idx];
    if (! valid_response (q)) {
      var section = document.getElementById("s_"+q.id);
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
  let elt = ev.target;
  let checked = elt.checked;

  if (checked) {
    $("#s_availability input[type=radio]").each (function (idx, elt) {
      if (! $(elt).is(":hidden")) {
	if ($(elt).val() == 1)
	  $(elt).prop ("checked", true);
      }
    });

    $(".sched_all_day").prop ("checked", true);
  }
}

function do_all_day (ev) {
  var elt = ev.target;
  var checked = elt.checked;
  var day = $(elt).data("day");

  if (checked) {
    $("#s_availability input[type=radio]").each (function (idx, elt) {
      if (! $(elt).is(":hidden")) {
	if ($(elt).data("day") == day && $(elt).val() == 1) {
	  $(elt).prop ("checked", true);
	}
      }
    });
  }

  $("#sched_any").prop ("checked", false);
}

function do_sched_item (ev) {
  $("#sched_any").prop ("checked", false);
  $(".sched_all_day").prop ("checked", false);
}

function do_session_option (ev) {
  var elt = ev.target;
  var val = elt.checked ? 1 : 0;
  $.get ("set_session_option.php", {
    "var": elt.id,
    "val": val
  });
}

$(function () {
  $("input[type='radio']").change (update_hides);
  $("#apply_form").submit (apply_submit);

  $("#sched_any").change (do_sched_any);
  $(".sched_all_day").change (do_all_day);
  $(".sched_item").change (do_sched_item);

  if (window.questions)
    update_hides ();

  setup_lookups ();
  
  $("#add_another").click(do_add_another);
  
  $("#show_all").change (do_session_option);
  $("#all_optional").change (do_session_option);

});
