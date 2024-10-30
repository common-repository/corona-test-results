(function($) { document.addEventListener('DOMContentLoaded', function() {

  // dismissable notices
  jQuery(function($) {
    $( document ).on( 'click', '[data-dismiss-id] .notice-dismiss', function () {
        const id = $( this ).closest( '[data-dismiss-id]' ).data( 'dismiss-id' );
        $.ajax( ajaxurl,
          {
            type: 'POST',
            data: {
              action: 'corona_test_results_dismiss_notice',
              id: id,
            }
          } );
      } );
  });

  const is_iOS = (() => {
    return ( navigator?.platform && [
      'iPad Simulator',
      'iPhone Simulator',
      'iPod Simulator',
      'iPad',
      'iPhone',
      'iPod'
    ].includes(navigator?.platform) ) ||
    ( navigator?.platform === 'MacIntel' && navigator?.maxTouchPoints > 1 );
  })();

  function downloadBlob(blob, fileName) {
    if ('msSaveOrOpenBlob' in window.navigator) {
      window.navigator.msSaveOrOpenBlob(blob, fileName);
      return;
    }

    const blobUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = fileName;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(function() {
      URL.revokeObjectURL(link.href)
    }, 1000 );
  }

  function handleFileDownload( docDefinition, fileName, callback ) {
    if ( ! callback ) {
      callback = () => {};
    }

    if ( is_iOS ) {
      pdfMake.createPdf(docDefinition).getBuffer().then(buffer => {
        const blob = new Blob([buffer], { type: 'application/octet-stream' });
        downloadBlob(blob, fileName);
      }).then(callback);
    } else {
      pdfMake.createPdf(docDefinition).download(fileName).then(callback);
    }
  }

  let pageSelects = document.querySelectorAll('.vt-select-page');
  if (pageSelects && pageSelects.length) {
    // for IE11...
    pageSelects = [].slice.call(pageSelects);
    pageSelects.forEach(function (el) {
      el.addEventListener( 'change', function (ev) {
        const select = ev.target;
        const actionLinksWrapper = select.nextElementSibling;
        const actionLinks = actionLinksWrapper.querySelectorAll('a');
        const previewLink = actionLinks[0];
        const editLink = actionLinks[1];
        previewLink.setAttribute('href', previewLink.getAttribute('href').replace(/page_id=\d+/, 'page_id=' + select.value));
        editLink.setAttribute('href', editLink.getAttribute('href').replace(/post=\d+/, 'post=' + select.value));
        actionLinksWrapper.style.display = select.value !== '0' ? '' : 'none';
      });
    });
  }

  let disabledFields = 0;
  // cannot use [name=""] for IE11...
  let emptyFields = document.querySelectorAll('[name]');
  // for IE11...
  emptyFields = [].slice.call(emptyFields);
  emptyFields.forEach(function (field) {
    if (!field.name) {
      field[field.options || field.type == 'checkbox' || field.type == 'radio' ? 'disabled' : 'readOnly'] = true;
      disabledFields++;
    }
  });
  if ( disabledFields === document.querySelectorAll('.form-table select,.form-table textarea,.form-table input[type="checkbox"],.form-table input[type="color"],.form-table input[type="date"],.form-table input[type="datetime-local"],.form-table input[type="email"],.form-table input[type="file"],.form-table input[type="month"],.form-table input[type="number"],.form-table input[type="password"],.form-table input[type="radio"],.form-table input[type="range"],.form-table input[type="search"],.form-table input[type="tel"],.form-table input[type="text"],.form-table input[type="time"],.form-table input[type="url"],.form-table input[type="week"]').length) {
    document.querySelector('input[type="submit"][name="submit"]').disabled = true;
  }

  // image picker
  let imagePickers = document.querySelectorAll('[data-vt-image-picker]');
  // for IE11...
  imagePickers = [].slice.call(imagePickers);
  imagePickers.forEach(function(pickerInput) {
		let mediaUploader;
		let $mediaInput = $(pickerInput);

		let $mediaButton = $('<input id="' + $mediaInput.attr('id') + '_picker" type="button" class="button-primary" value="' + corona_test_results_settings.picker_btn_select + '" />').insertAfter($mediaInput);
		let $mediaPreview = $('<img id="' + $mediaInput.attr('id') + '_preview" style="cursor: pointer; display: none;" alt="" />')
      .insertBefore($mediaInput)
      .on('error', function() {
        this.style.display = 'none';
      })
      .on('load', function() {
        this.style.display = 'block';
        let regularText = document.querySelector('.regular-text');
        this.style.maxWidth = regularText ? document.querySelector('.regular-text').offsetWidth + 'px' : '50vw';
      });
		let mediaCurrentUrl = $mediaInput.attr('data-vt-image-picker');

		if (mediaCurrentUrl.length) {
			$mediaPreview.attr( 'src', mediaCurrentUrl );
		}

		$($mediaButton).add($mediaPreview).click(function(e) {
		  e.preventDefault();
			if (mediaUploader) {
			mediaUploader.open();
			return;
		  }
		  mediaUploader = wp.media.frames.file_frame = wp.media({
			title: 'Choose Image',
			button: {
			text: 'Choose Image'
		  }, multiple: false });
		  mediaUploader.on('select', function() {
			const attachment = mediaUploader.state().get('selection').first().toJSON();
			$mediaInput.val(attachment.url);
			$mediaPreview.attr('src', attachment.url);
		  });
		  mediaUploader.open();
		});

		$($mediaInput).on('input', function (e) {
			$mediaPreview.attr('src', e.target.value);
		});
  });

  const deletionDataCheckbox = document.getElementById('corona_test_results_opts_security_deletion_data');
  const deletionKeyCheckbox = document.getElementById('corona_test_results_opts_security_deletion_key');
  let deletionKeyStateBefore = false;
  if ( deletionDataCheckbox && deletionKeyCheckbox ) {
    const disableDeletionKeyCheckbox = function() {
      deletionKeyStateBefore = deletionKeyCheckbox.checked;
      deletionKeyCheckbox.checked = false;
      deletionKeyCheckbox.disabled = true;
    };
    if ( ! deletionDataCheckbox.checked ) {
      disableDeletionKeyCheckbox();
    }
    deletionDataCheckbox.addEventListener( 'change', function() {
      if ( this.checked ) {
        deletionKeyCheckbox.disabled = false;
        deletionKeyCheckbox.checked = deletionKeyStateBefore;
      } else {
        disableDeletionKeyCheckbox();
      }
    });
  }

})})(jQuery);
