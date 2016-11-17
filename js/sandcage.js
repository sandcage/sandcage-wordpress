
window.log=function(){log.history=log.history||[];log.history.push(arguments);if(this.console){console.log(Array.prototype.slice.call(arguments))}};

jQuery(document).ready(function($) {
	removeParam('sc_msg');
	removeParam('sc_error');
	window.sc_wpcontent_l_pad = $('#wpcontent').css('padding-left');
	window.sc_wpadminbar_zindex = $('#wpadminbar').css('z-index');

	if ( typeof $('#sandcage-conf').html() !== "undefined" ) {
		window.sandcage_conf = $('#sandcage-conf');
		if ( typeof window.sandcage_conf.attr('data-src') !== "undefined" ) {

			try {
				$('#add_media_from_sandcage').off('click');
				$('#add_media_from_sandcage').on('click', function(){
					addMediaFromSandCage()
				});
			}
			catch(e) {
				$('#add_media_from_sandcage').unbind('click');
				$('#add_media_from_sandcage').bind('click', function(){
					addMediaFromSandCage()
				});
			}

		} else {
			window.log('on the media page');
			setTimeout(function(){
				adminStyling();
				resizeIFrame();
				$(window).resize(resizeIFrame);
			}, 1);
		}
	} else if ( typeof $('#sandcage-media-library-list').html() !== "undefined" ) {
		setTimeout(function(){
			checkForPendingFiles();
		}, 1000);
	}

	function addMediaFromSandCage() {
		if ($('#sandcage-asset-frame').length == 0) {
			var p = '<div id="sandcage-asset-frame-wrapper">' + 
				'<iframe name="sandcage-asset-frame" id="sandcage-asset-frame" src="' +
					window.sandcage_conf.data("src") +
					'" style="width:100%"></iframe>' +
				'</div>';
			$(p).appendTo($('#wpbody-content'));
		}else{
			adminStyling();
		}
		$('.sandcage_message').html('');
		resizeIFrame();
		$('#sandcage-asset-frame-wrapper').show();
		return false;
	}

	function checkForPendingFiles() {
		var pending_files = [];
		$( ".pending-processing-on-sandcage" ).each(function( index ) {
			pending_files.push( $(this).data("attachment-id") );
		});

		if ( pending_files.length > 0 ) {
			$.ajax({
				type: "POST",
				url: $('#sandcage-media-library-list').data("admin-ajax"),
				data: {
					action: 'add_media_from_sandcage',
					ids: pending_files.join()
				},
				cache:false,
				success:function(h){
					if ((typeof h.status !== "undefined") && (h.status == 'success')) {
						if (h.ids.length > 0) {
							var complete_files = [];
							for (var i= h.ids.length; i-->0;) {
								if ( !h.ids[i].pending ) {
									complete_files.push( h.ids[i].id );
									for (var d= pending_files.length; d-->0;) {
										if ( h.ids[i].id == pending_files[d] ) {
											pending_files.splice(d, 1);
											break;
										}
									}
								}
							}
							if ( complete_files.length > 0 ) {
								for (var i= complete_files.length; i-->0;) {
									$('#attachment-' + complete_files[i] + '>span').html('On SandCage');
									$('#attachment-' + complete_files[i] + '>img').removeClass('pending-processing-on-sandcage');
								}
							}
							if ( pending_files.length > 0 ) {
								setTimeout(function(){
									checkForPendingFiles();
								}, 10000);
							}
						}
					} else {
						setTimeout(function(){
							checkForPendingFiles();
						}, 30000);
					}
				},
				error:function(){
					setTimeout(function(){
						checkForPendingFiles();
					}, 15000);
				}
			});
		}

	}

	function hideSandCageIFrame() {
		$('#sandcage-asset-frame-wrapper').hide();
		$('#wpcontent').css('padding-left', window.sc_wpcontent_l_pad);
		$('#wpadminbar').css('z-index', window.sc_wpadminbar_zindex);
	}

	function adminStyling() {
		$('#wpcontent').css('padding-left', '0');
		$('#wpadminbar').css('z-index', '100102');
	}    

	function resizeIFrame(height) {
		var f_height = 0;
		var wpwrap = $('#wpwrap').size() > 0 ? $('#wpwrap').outerHeight(true) : 0;
		var footer = $('#footer').size() > 0 ? $('#footer').outerHeight(true) : $('#wpfooter').outerHeight(true);
		if (!$('#wpfooter').is(":visible")) {
			footer = 0;
		}
		var f_height = wpwrap - footer;
		if (f_height < 150) {
			f_height = 150;
		}
		$('#wpbody').css('height', f_height).css('overflow', 'hidden');
		$('#sandcage-asset-frame').css('height', f_height);  
	}

		
	function addMediaFromSC(h) {
		var attr = {
			src: h.src
		};
		if ((typeof h.w !== "undefined") && !!h.w){
			attr.width = h.w;
		}
		if ((typeof h.h !== "undefined") && !!h.h){
			attr.height = h.h;
		}
		if ((typeof h.title !== "undefined") && !!h.title){
			attr.title = h.title;
		}
		if ((typeof h.alt !== "undefined") && !!h.alt){
			attr.alt = h.alt;
		}
		var image = $('<img/>').attr(attr);
		var data = {
			action: 'add_media_from_sandcage',
			src: h.src,
			sandcage_file_token: h.file_id,
			w: h.w,
			h: h.h,
			mime: h.mime,
			name: h.title,
			post_id: $('#post_ID').val()
		};

		if (foundTinyMCEActiveEditorSelection()) {
			var html = tinyMCE.activeEditor.selection.getContent({format:'html'});
			var match = html.match(/wp-image-(\d+)/);
			if (match) {
				data.attachment_id = match[1];
			}
		}

		$.ajax({
			type: "POST",
			url: window.sandcage_conf.data("admin-ajax"),
			data: data,
			cache:false,
			success:function(h){
				window.log(h);
				if ((typeof h.status !== "undefined") && (h.status == 'success')) {
					image.addClass('wp-image-' + h.attachment_id).addClass('alignnone').addClass('size-full').attr('alt', data.name).attr('data-mce-src', data.src);
				} else {
					alert(h.message);
					if (data.attachment_id) {
						image.addClass('wp-image-' + data.attachment_id);
					}
				}
				image = $('<a/>').attr('href', data.src).attr('title', data.name).attr('data-mce-href', data.src).append(image);
				if (foundTinyMCEActiveEditorSelection()) {
					tinyMCE.activeEditor.selection.setContent($('<div/>').append(image).html()); 
				} else {
					send_to_editor($('<div/>').append(image).html());  
				}
			},
			error:function(){
				alert('An error occurred while adding the file.');
			}
		});
	}

	function foundTinyMCEActiveEditorSelection() {
		if (
				(typeof tinyMCE !== "undefined") && (typeof tinyMCE.activeEditor !== "undefined") && (typeof tinyMCE.activeEditor.selection !== "undefined") && 
				tinyMCE && tinyMCE.activeEditor && tinyMCE.activeEditor.selection
			) {
			return true;
		} else {
			return false;
		}
	}

	function removeParam(parameter) {
		var url=document.location.href;
		var urlparts= url.split('?');

		if (urlparts.length>=2) {
			var urlBase=urlparts.shift(); 
			var queryString=urlparts.join("?"); 

			var prefix = encodeURIComponent(parameter)+'=';
			var pars = queryString.split(/[&;]/g);
			for (var i= pars.length; i-->0;) {
				if (pars[i].lastIndexOf(prefix, 0)!==-1) {
					pars.splice(i, 1);
				}
			}
			url = urlBase+'?'+pars.join('&');
			window.history.pushState('',document.title,url); // added this line to push the new url directly to url bar.
		}
		return url;
	}

	function listenMessage(msg) {
		if (msg) {
			if (msg.data && msg.data.action) {
				if (msg.data.action == 'close_frame') {
					hideSandCageIFrame(window.sc_wpcontent_l_pad);
				} else if (msg.data.action == 'sandcage_frame_loaded') {
					adminStyling();
				} else if ((msg.data.action == 'add_img_to_wp_post') && !!msg.data.info) {
					addMediaFromSC(msg.data.info);
					hideSandCageIFrame();
				}
			}
			window.log(msg);
		}
	}

	if (window.addEventListener) {
		window.addEventListener("message", listenMessage, false);
	} else {
		window.attachEvent("onmessage", listenMessage);
	}
});