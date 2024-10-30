(function($) {
  /**
   * Sanitize and encode all HTML in a user-submitted string
   * https://portswigger.net/web-security/cross-site-scripting/preventing
   * @param  {String} str  The user-submitted string
   * @return {String} str  The sanitized string
   */
  var sanitizeHTML = function (str) {
    return str.replace(/[^\w. ]/gi, function (c) {
      return '&#' + c.charCodeAt(0) + ';';
    });
  };

  function debounce(func, timeout = 300){
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => { func.apply(this, args); }, timeout);
    };
  }

  function mysqlDatetimeToJSDate( mysqlDateTime ) {
    let dateTimeParts = ( mysqlDateTime ).split( /[- :]/ );
    dateTimeParts[1]--;

    return new Date( ...dateTimeParts );
  }

  function pdfmakeMMtoPointUnit( mm ) {
    const MILLIMETERS_IN_INCH = 25.4;
    const POINTS_IN_INCH = 72;

    const inches = mm / MILLIMETERS_IN_INCH;
    return inches * POINTS_IN_INCH;
  }

  let couldQueryCameraPermission = false;
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
  };

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

  document.addEventListener('DOMContentLoaded', function() {
    const debug = false;
    const isAssignation = !!document.getElementById('corona_test_results_assign');
    const isRegistration = !!document.getElementById('corona_test_results_register');
    const isIE11 = !!window.MSInputMethodContext && !!document.documentMode;
    const docLocale = document.documentElement.getAttribute('lang');
    const customFieldCount = corona_test_results._customize && typeof corona_test_results._customize.customfields_count !== 'undefined'
      ? corona_test_results._customize.customfields_count
      : 3;

    const pinsDisabled = !!corona_test_results._customize && !!corona_test_results._customize.pins_disabled;
    const pinsBday = corona_test_results?._customize?.pins_bday;
    const allowCertResub = !!corona_test_results._customize && !!corona_test_results._customize.allow_cert_resend;
    const manualCodes = !!corona_test_results._customize && ! corona_test_results._customize.code_readonly;

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

    if ( typeof pdfMake !== 'undefined' ) {
      pdfMake.fonts = {
        Roboto: {
          normal: 'Roboto-Regular.ttf',
          bold: 'Roboto-Medium.ttf',
          italics: 'Roboto-Italic.ttf',
        },
        RobotoMono: {
          normal: 'RobotoMono-Regular.ttf'
        },
        RobotoBold: {
          normal: 'Roboto-Bold.ttf'
        }
      };
    }

    switch(true) {
      /**
       * Test result assignation
       */
      case isAssignation:
        const assignationForm = document.getElementById('corona-test-results-form');
        const searchField = document.getElementById('corona_test_results_search');
        const paginationContainer = document.querySelector('.tablenav-pages');
        const exportButton = document.getElementById('corona_test_results_export');
        const resultsTable = document.getElementById('corona_test_results_table');
        const resultsTableBody = resultsTable.querySelector('tbody');
        const checkedStates = new Set([]);
        const checkAllBox = document.getElementById('cb-select-all-1');
        const fetchData = { additionalRowData: null };
        const disableInteractiveElements = document.querySelectorAll( '#corona_test_results_assign input, #corona_test_results_assign button, #corona_test_results_assign select');

        let dirtyStates = [];
        let isFormSubmitting = false;

        searchField.disabled = false;
        exportButton.disabled = false;

        let resultsTableRows = [].slice.call(resultsTableBody.querySelectorAll('tr'));

        const registerStateHandlers = function() {
          let selects = [];
          let checkboxes = [];
          resultsTableRows.map(function(row) {
            selects.push( row.querySelector('select') );
            checkboxes.push( row.querySelector('input[type="checkbox"][name="code[]"]') );
          });

          const makeDirty = function(s) {
            dirtyStates.push(s);
            if ( corona_test_results.certificates_enabled ) {
              const stati_disable_certs = corona_test_results?._customize?.stati_disable_certs;
              const status_key = parseInt(s.value, 10);
              const disable_cert = status_key === 0 || ( stati_disable_certs && stati_disable_certs.includes( status_key ) )
              s.nextElementSibling.classList[disable_cert ? 'add' : 'remove']('hidden');
            }
          };

          window.addEventListener('beforeunload', function(ev) {
            if (dirtyStates.length && !isFormSubmitting) {
              ev.preventDefault();
              ev.returnValue = '';
              return '';
            }

            delete ev['returnValue'];
            return undefined;
          });

          selects.forEach(function(s) {
            s && s.addEventListener('change', function() { makeDirty(s) });
          });

          // init checkedStates if some have been checked before state handlers have been registered
          const visibleCheckBoxes = assignationForm.querySelectorAll('[name="code[]"]');
          if ( visibleCheckBoxes && visibleCheckBoxes.length ) {
            visibleCheckBoxes.forEach( function( cb ){
              if ( cb.checked ) {
                checkedStates.add( cb.value );
              }
            } );
          }

          checkboxes.forEach(function(c) {
            c && c.addEventListener('change', function( ev ) {
              // we can't simply add or remove this one checkbox, because we have to account for shift key functionality
              // checkedStates[this.checked ? 'add' : 'delete'](this.value);

              let visibleBoxes = resultsTableBody.querySelectorAll('input[type="checkbox"][name="code[]"]')
              // for IE11...
              visibleBoxes = [].slice.call(visibleBoxes);
              visibleBoxes.forEach(function(c) {
                checkedStates[c.checked ? 'add' : 'delete'](c.value);
              });
            });
          });
        }

        const searchFilter = function() {
          const searchString = searchField.value.toUpperCase();

          if (paginationContainer) {
            paginationContainer.style.display = searchString.length ? 'none' : '';
            if (!searchString.length && typeof updatePageItems !== 'undefined') {
              updatePageItems();
              return;
            }
          }

          if (resultsTableRows.length) {
            const useFilterHook =
              !!corona_test_results._customize &&
              !!corona_test_results._customize.assignation &&
              typeof corona_test_results._customize.assignation.searchFilterIsMatching === 'function'
              ? corona_test_results._customize.assignation.searchFilterIsMatching : false;

            resultsTableRows.forEach(function(r) {
              let firstColumn = r.querySelector('td');
              let rowCode = firstColumn.textContent.toUpperCase();
              let isMatching = rowCode.indexOf(searchString) === 0;

              if ( useFilterHook ) {
                const isMatchingFiltered = useFilterHook(
                  isMatching,
                  r,
                  searchString,
                  rowCode,
                  searchField.value
                );
                if ( typeof isMatchingFiltered !== 'undefined' && isMatchingFiltered !== null) {
                  isMatching = isMatchingFiltered;
                }
              }

              if (isMatching) {
                resultsTableBody.appendChild(r);
                r.style.display = '';
              } else if (r.parentNode === resultsTableBody) {
                resultsTableBody.removeChild(r)
              }
            });
          }
        };

        checkAllBox.addEventListener('change', function() {
          let visibleBoxes = resultsTableBody.querySelectorAll('input[type="checkbox"][name="code[]"]')
          // for IE11...
          visibleBoxes = [].slice.call(visibleBoxes);
          visibleBoxes.forEach(function(c) {
            const changeEvent = document.createEvent("HTMLEvents");
            changeEvent.initEvent("change", false, true);
            c.dispatchEvent(changeEvent);
          });
        });

        // pagination
        let updatePageItems;
        if (paginationContainer) {
          const btnFirst = paginationContainer.querySelector('.button.first-page');
          const btnPrev = paginationContainer.querySelector('.button.prev-page');
          const btnNext = paginationContainer.querySelector('.button.next-page');
          const btnLast = paginationContainer.querySelector('.button.last-page');

          const itemsPerPage = parseInt(paginationContainer.querySelector('#pagination-items-per-page').value, 10);
          const totalPages = parseInt(paginationContainer.querySelector('.total-pages').textContent.trim(), 10);
          const currentPageInput = paginationContainer.querySelector('#current-page-selector');
          const pagedFormInput = assignationForm.querySelector('[name="paged"]');
          let currentPage = parseInt(currentPageInput.value, 10);

          const updatePagination = function() {
            btnFirst.disabled = currentPage <= 2;
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage === totalPages;
            btnLast.disabled = currentPage >= totalPages - 1;
            currentPageInput.value = pagedFormInput.value = currentPage;
          };

          btnFirst.addEventListener('click', function() {
            currentPage = 1;
            updatePageItems();
          });

          btnPrev.addEventListener('click', function() {
            currentPage--;
            updatePageItems();
          });

          btnNext.addEventListener('click', function() {
            currentPage++;
            updatePageItems();
          });

          btnLast.addEventListener('click', function() {
            currentPage = totalPages;
            updatePageItems();
          });

          updatePageItems = function() {
            const itemsFrom = (currentPage - 1) * itemsPerPage;
            const itemsTo = itemsFrom + itemsPerPage - 1;

            resultsTableRows.forEach(function(item, index) {
              const isMatching = index >= itemsFrom && index <= itemsTo;
              if (isMatching) {
                resultsTableBody.appendChild(item);
                item.style.display = '';
              } else if (item.parentNode === resultsTableBody) {
                resultsTableBody.removeChild(item)
              }
            });

            let selectedRows = resultsTableBody.querySelectorAll('input[type="checkbox"][name="code[]"]:checked').length;
            let rowCount = resultsTableBody.querySelectorAll('tr').length;

            checkAllBox.checked = ( selectedRows && selectedRows === rowCount );

            updatePagination();
          };

          currentPageInput.addEventListener('input', function() {
            let newPage = parseInt(this.value);
            if (isNaN(newPage) || newPage < 1 || newPage > totalPages) {
              return;
            }
            currentPage = newPage;
            updatePageItems();
          });
          currentPageInput.addEventListener('blur', function() {
            this.value = currentPage;
          });

          // fetch remaining rows via ajax
          if (resultsTableRows.length && 'ctr_fetch_hidden_rows' in window) {
            let selectTemplate = document.createElement('select');
            for (const [key, status] of Object.entries(corona_test_results.stati)) {
              let option = document.createElement('option');
              option.value = key;
              option.innerText = status;
              selectTemplate.appendChild( option );
            };

            const loadingRow = document.createElement('tr');
            loadingRow.innerHTML = '<td colspan="4">' + corona_test_results.rows_loading + '</td>';
            resultsTableBody.appendChild(loadingRow);

            $.ajax( ajaxurl,
              {
                type: 'POST',
                data: {
                  action: 'corona_test_results_fetch_rows',
                  stati: window.ctr_fetch_hidden_rows,
                  _wpnonce: corona_test_results.nonces.fetch_rows
                },
                success: function (data) {
                  if ( typeof data !== 'undefined' && Array.isArray(data) ) {
                    data.forEach(function (row) {
                      let statusTemplate = '';

                      const iconsMarkup = '';

                      const certButtonLabel = corona_test_results.certificateTexts.generate;
                      const certButton = '<a href="#TB_inline?&inlineId=certificate-modal" class="button button-link button-generate-certificate thickbox' + ( row.s === 0 ? ' hidden' : '') + '" title="' + certButtonLabel + '">' + certButtonLabel + '</a>';

                      if ( selectTemplate && ! window.ctr_fetch_hidden_rows.includes('trash') && typeof row.cs === 'undefined' ) {
                        let selectedOption = selectTemplate.querySelector('[selected]');
                        if (selectedOption) {
                          selectedOption.removeAttribute('selected');
                        }
                        selectTemplate.querySelector('option[value="' + row.s + '"]').setAttribute('selected', true);
                        selectTemplate.name = 'corona_test_results_result_status[' + row.c + ']';

                        statusTemplate = iconsMarkup + '' + selectTemplate.outerHTML;
                      }

                      const tr = document.createElement('tr');
                      tr.classList.add('is-expanded');
                      const statusMarkup = ( statusTemplate ? statusTemplate : iconsMarkup + '<p class="status">' + corona_test_results.stati[row.s] + '</p>' );
                      tr.innerHTML = '<th scope="row" class="check-column"><input id="cb-select-' + row.c +  '" type="checkbox" name="code[]" value="' + row.c +  '"></th>'
                        + '<td class="column-primary"><code>' + row.c +  '</code></td>'
                        + '<td data-colname="' + corona_test_results.columnHeaders[1] + '"' + '>'
                            + statusMarkup
                        + '</td>'
                        + '<td data-colname="' + corona_test_results.columnHeaders[2] + '" data-datetime="' + row.t + '">' + row.d + '</td>';

                      resultsTableRows.push(tr);
                    });
                    resultsTableBody.removeChild(loadingRow);
                    // updatePageItems(); // will be executed by searchFilter() anyway
                    updatePagination();
                    registerStateHandlers();
                    searchFilter();
                  } else {
                    console.error(corona_test_results.rows_loading_failed);
                  }
                },
                error: function() {
                  console.error(corona_test_results.rows_loading_failed);
                  loadingRow.firstChild.innerHTML = '<span style="color: red;">' + corona_test_results.rows_loading_failed + '</span>';
                  updatePagination();
                  registerStateHandlers();
                }
            } );
          } else {
            updatePagination();
            registerStateHandlers();
          }
        } else {
          registerStateHandlers();
        }

        searchField.addEventListener('input', searchFilter);

        exportButton.addEventListener('click', function() {
          alert(corona_test_results.premiumFeatureNotice);
        });

        const actionList = document.getElementById( 'bulkactions' );
        const actionButton = document.getElementById( 'dobulkaction' );
        actionButton.addEventListener('click', function(ev) {
          switch (actionList.options[actionList.selectedIndex].value) {
            case 'marknegative':
              if ( checkedStates.size ) {
                resultsTableRows.forEach(function(r) {
                  let firstColumn = r.querySelector('td');
                  let rowCode = firstColumn.textContent.toUpperCase();
                  if ( checkedStates.has( rowCode ) ) {
                    let statusSelect = r.querySelector('select');
                    if ( statusSelect ) {
                      statusSelect.querySelector('[selected]').removeAttribute('selected');
                      statusSelect.querySelector('option[value="2"]').setAttribute('selected', true);
                      const changeEvent = document.createEvent("HTMLEvents");
                      changeEvent.initEvent("change", false, true);
                      statusSelect.dispatchEvent(changeEvent);
                    }
                  }
                });
              }
              break;
            case 'trash':
            case 'untrash':
            case 'delete':
              return true;
          }

          ev.preventDefault();
        });

        assignationForm.addEventListener('submit', function(ev) {
          isFormSubmitting = true;

          const hiddenStateChanges = {};

          if (dirtyStates.length) {
            dirtyStates.forEach(function(select) {
              // if(!isConnected(select)) {
                hiddenStateChanges[select.parentNode.parentNode.querySelector('input[type="checkbox"][name="code[]"]').value] = select.options[select.selectedIndex].value;
              // }
            });

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'corona_test_results_result_status_hidden';
            hiddenInput.value = JSON.stringify(hiddenStateChanges);
            assignationForm.appendChild(hiddenInput);
          }

          if (checkedStates.size) {
            const hiddenCheckInput = document.createElement('input');
            hiddenCheckInput.type = 'hidden';
            hiddenCheckInput.name = 'corona_test_results_result_checked';
            if (isIE11) {
              const checkedArray = [];
              checkedStates.forEach(function(s) {
                checkedArray.push(s);
              });
              hiddenCheckInput.value = JSON.stringify(checkedArray);
            } else {
              hiddenCheckInput.value = JSON.stringify(Array.from(checkedStates));
            }
            assignationForm.appendChild(hiddenCheckInput);
          }
        });

        corona_test_results._customize._var = {
          fetchData: fetchData,
          resultsTableRows: resultsTableRows
        };

        break;
      /**
       * Code and PDF generation
       */
      case isRegistration:
        const form = document.getElementById('corona-test-results-form');
        const submitBtn = document.getElementById('submit');
        const privacyHint = document.getElementById('privacy-hint');
        const popupHint = document.getElementById('popup-hint');
        const generateBtn = document.getElementById('generate-pdf');
        const resetBtn = document.getElementById('reset-form');
        const printlabelBtn = document.getElementById('generate-label');
        const codeField = document.getElementById('test_result_code');
        const requestCert = document.getElementById('certificate_required');
        const pinField = document.getElementById('test_result_pin');
        const message = document.getElementById('message');
        const dataTransferResponses = {};

        let codeBefore = null;
        let codeUpdateNonce = null;
        let codeCreatedAt = null;

        function disableButtons() {
          submitBtn.disabled = true;
          generateBtn.disabled = true;
        }

        function enableButtons() {
          submitBtn.disabled = false;
          generateBtn.disabled = false;
        }

        function switchButtons() {
          // switch submit and generate button, show reset button
          submitBtn.style.display = 'none';
          privacyHint.style.display = 'none';
          popupHint.style.display = 'block';
          generateBtn.style.display = '';
          resetBtn.style.display = '';
          printlabelBtn.style.display = '';
        }

        function resetForm() {
          codeBefore = null;
          codeUpdateNonce = null;
          codeCreatedAt = null;
          let inputs = form.querySelectorAll( isIE11 ? 'input, textarea' : 'input:not(#qrscanner-modal *), textarea:not(#qrscanner-modal *)');
          // for IE11...
          inputs = [].slice.call(inputs);

          inputs.forEach(function(i) {
            if ( i.type === 'checkbox' ) {
              if (
                requestCert
                && i === requestCert
                && typeof corona_test_results.cfg.certificates_default !== 'undefined'
                && corona_test_results.cfg.certificates_default === 'on'
              ) {
                i.checked = true;
              } else {
                i.checked = false;
              }

              var event = document.createEvent("HTMLEvents");
              event.initEvent("change", false, true);
              i.dispatchEvent(event);
            } else if ( i.type === 'radio' ) {
              i.checked = i.hasAttribute('data-vt-default');
            } else {
              i.value = '';
              if ( i.hasAttribute('data-appointment-filter') ) {
                let visibleOptions = document.getElementById('appointments-' + i.getAttribute('data-appointment-filter')).querySelectorAll('option:not([value=""])');
                if ( visibleOptions && visibleOptions.length ) {
                  // for IE11...
                  visibleOptions = [].slice.call(visibleOptions);
                  visibleOptions.forEach(function(o) {
                    o.hidden = false;
                  });
                }
              }
            }
          });

          submitBtn.style.display = '';
          privacyHint.style.display = 'block';
          popupHint.style.display = 'none';
          generateBtn.style.display = 'none';
          resetBtn.style.display = 'none';
          printlabelBtn.style.display = 'none';
        }

        function handleAjaxErrors(response, isBatch) {
          if (typeof isBatch === 'undefined') {
            isBatch = false;
          }

          // coult be const, but for IE11 array workaround...
          let paragraphs = message.querySelectorAll('p');
          let activeParagraph;
          if (response.responseJSON && response.responseJSON.data && response.responseJSON.data.error) {
            // error during code generation
            activeParagraph = 'codegen' + (isBatch ? '-batch' : '');
          } else {
            // something else went wrong
            activeParagraph = 'ajax';
          }

          const errorDetails = response && response.responseJSON && response.responseJSON.data ? response.responseJSON.data : false;
          const errorMessage = errorDetails.error && ( typeof errorDetails.error === 'string' || errorDetails.error instanceof String ) ? errorDetails.error : null;
          const detailsParagraph = message.querySelector('.vt-error-details');

          // for IE11...
          paragraphs = [].slice.call(paragraphs);
          paragraphs.forEach(function (p) {
            const isMessageParagraph = p.classList.contains('vt-error-' + activeParagraph);
            p.style.display = isMessageParagraph && ! errorMessage ? 'block' : 'none';
            if ( isMessageParagraph ) {
              document.body.scrollIntoView({behavior: 'smooth'});
            }
          });

          const hasDetails = errorDetails && ( ! errorDetails.error || errorDetails.error !== 1 );

          detailsParagraph.textContent =
          hasDetails ?
              ( errorMessage
                ? errorMessage
                : JSON.stringify( errorDetails )
              ) : '';
          detailsParagraph.style.display = hasDetails ? 'block' : 'none';

          message.style.display = 'block';
          submitBtn.disabled = false;
          generateBtn.disabled = false;
          batchBtn.disabled = false;
          batchBtn.classList.remove('loading');
        }

        /**
         * pdfmake extensions
         */
        function mapTableBodies(innerTableCell) {
          const findInlineHeight = this.findInlineHeight(
            innerTableCell,
            maxWidth,
            usedWidth
          );

          usedWidth = findInlineHeight.width;
          return findInlineHeight.height;
        }

        function findInlineHeight(cell, maxWidth, usedWidth) {
          usedWidth = typeof usedWidth === 'undefined' ? 0 : usedWidth;
          let calcLines = function(inlines) {
            if (!inlines)
              return {
                height: 0,
                width: 0,
              };
            let currentMaxHeight = 0;
            let lastHadLineEnd = false;
            inlines.forEach(function(currentNode) {
              usedWidth += currentNode.width;
              if (usedWidth > maxWidth || lastHadLineEnd) {
                currentMaxHeight += currentNode.height;
                usedWidth = currentNode.width;
              } else {
                currentMaxHeight = Math.max(currentNode.height, currentMaxHeight);
              }
              lastHadLineEnd = !!currentNode.lineEnd;
            });

            return {
              height: currentMaxHeight,
              width: usedWidth,
            };
          }
          if (cell._offsets) {
            usedWidth += cell._offsets.total;
          }
          if (cell._inlines && cell._inlines.length) {
            return calcLines(cell._inlines);
          }  else if (cell.stack && cell.stack[0]) {
            return cell.stack.map(function(item) {
              return findInlineHeight(item, maxWidth);
            }).reduce(function(prev, next) {
              return {
              height: prev.height + next.height,
              width: Math.max(prev.width + next.width)
              };
            });
          } else if (cell.table) {
            let currentMaxHeight = 0;
            cell.table.body.forEach(function(currentTableBodies) {
              const innerTableHeights = currentTableBodies.map(mapTableBodies);
              innerTableHeights.push(currentMaxHeight);
              currentMaxHeight =  Math.max.apply(this, innerTableHeights);
            })
            return {
              height: currentMaxHeight,
              width: usedWidth,
            };
          } else if (cell._height) {
            usedWidth += cell._width;
            return {
              height: cell._height,
              width: usedWidth,
            };
          }

          return {
            height: null,
            width: usedWidth,
          };
        }

        function applyVerticalAlignment(node, rowIndex, align) {
            const allCellHeights = node.table.body[rowIndex].map(
              function(innerNode, columnIndex) {
                const mFindInlineHeight = findInlineHeight(
                  innerNode,
                  node.table.widths[columnIndex]._calcWidth
                );
                return mFindInlineHeight.height;
              }
            );
            let maxRowHeight = Math.max.apply(this, allCellHeights);

            node.table.body[rowIndex].forEach(function(cell, ci) {
              if (allCellHeights[ci] && maxRowHeight > allCellHeights[ci]) {
                let topMargin;

                let cellAlign = align;
                if (Array.isArray(align)) {
                    cellAlign = align[ci];
                }

                if (cellAlign === 'bottom') {
                  topMargin = maxRowHeight - allCellHeights[ci];
                } else if (cellAlign === 'center') {
                  topMargin = (maxRowHeight - allCellHeights[ci]) / 2;
                }

                if (topMargin) {
                    if (cell._margin) {
                      cell._margin[1] = topMargin;
                    } else {
                      cell._margin = [0, topMargin, 0, 0];
                    }
                }
              }
            });
        }
        /**
         * EOF pdfmake extensions
         */

        function batchDisplayDateIsValid() {
          let overrideDate = isIE11 ? new Date(dateOverride.value) : mysqlDatetimeToJSDate( dateOverride.value + ' 00:00:00' );
          let maxDays = parseInt(corona_test_results.batch_override_date_max, 10);
          let realCurrentDate = (new Date());
          realCurrentDate.setHours(0,0,0,0);

          const diffTime = Math.abs(overrideDate - realCurrentDate);
          const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

          return !(overrideDate < realCurrentDate)
            && diffDays <= (maxDays + 1);
        }

        function getCurrentDisplayDate(isSingle) {
          let currentDate = codeCreatedAt ? mysqlDatetimeToJSDate( codeCreatedAt ) : new Date();
          let overrideDate = isIE11 ? new Date(dateOverride.value) : mysqlDatetimeToJSDate( dateOverride.value + ' 00:00:00' );
          if ( !isSingle && !!overrideDate && batchDisplayDateIsValid() ) {
            currentDate = overrideDate;
          }

          let options = {day: 'numeric', month: 'long', year: 'numeric'};
          if ( isSingle ) {
            options.hour = '2-digit';
            options.minute = '2-digit';
          }

          return new Intl.DateTimeFormat(docLocale, options).format(currentDate);
        }

        function prepareTemplateString(stringIn, isSingle) {
          let stringOut = stringIn;

          if (typeof isSingle === 'undefined') {
            isSingle = false;
          }

          if (!stringOut || !stringOut.length) return '';

          // hard-replace {{testdate}}
          let currentDate = isSingle ? getCurrentDisplayDate(isSingle) : lastBatchDisplayDate;

          stringOut = stringOut.replace( /\{\{testdate\}\}/g, currentDate, stringOut );

          for ( var i = 1; i <= customFieldCount; i++ ) {
            const cfName = 'customfield' + ( i > 1 ? i.toString() : '');
            // if the custom field is not used, but the custom field placeholder is present, remove it
            if ( typeof corona_test_results.cfg['template_' + cfName] === 'undefined' || !corona_test_results.cfg['template_' + cfName].length ) {
              // if it would cause a line break, remove that as well
              stringOut = stringOut.replace( new RegExp("((^|\r?\n)\s*\{\{" + cfName + "\}\}\s*(\r?\n|$))", 'g'), function( matches, match1, match2, contents, offset, input_string ) {
                if ( /\n/.test(match1) && /\n/.test(match2) ) {
                  return "\n";
                }

                return '';
              });
              // replace occurrences mid-string
              stringOut = stringOut.replace( new RegExp( "\{\{" + cfName + "\}\}", 'g' ), "");
            }
          }

          stringOut = stringOut.replace( /\{\{([a-z_]+\d*)\}\}/gi, function(matches, contents) {
            if (!isSingle) {
              return '';
            }

            let matchingInput = form.querySelector('[name="' + contents + '"]');
            let fieldValue = matchingInput ? matchingInput.value : null;

            if (fieldValue && matchingInput.type.toLowerCase() === 'date' && typeof window.Intl !== 'undefined' && typeof window.Intl.DateTimeFormat !== 'undefined') {
              try {
                fieldValue = new Intl.DateTimeFormat(docLocale, {day: 'numeric', month: 'long', year: 'numeric'}).format( isIE11 ? new Date(matchingInput.value) : matchingInput.valueAsDate);
              } catch(e) {}
            }
            return fieldValue ? fieldValue : '';
          });

          return stringOut.trim();
        }

        function parseTextMarkup(stringIn) {
          let textOut = [];

          const fragment = document.createDocumentFragment();
          const root = document.createElement('div');
          fragment.appendChild(root);
          const stringSanitized = sanitizeHTML( stringIn );
          const stringAllowSomeTags = stringSanitized.replace(/&#60;(&#47;|)(strong|b|em|i)&#62;/g, function(m1, m2, m3) {
            return '<' + (!!m2 ? '/' : '') + m3 + '>';
          });
          root.innerHTML = stringAllowSomeTags;

          const childNodes = [].slice.call(root.childNodes);
          childNodes.forEach(function(node) {
            recursiveMarkupParser(node, textOut, {});
          });
          return textOut;
        }

        function recursiveMarkupParser(node, textStack, props) {
          if (node.nodeType === 3) {
            textStack.push(Object.assign({}, { text: node.textContent }, props));
          } else {
            props.bold = props.bold || (node.tagName === 'B' || node.tagName === 'STRONG');
            props.italics = props.italics || (node.tagName === 'I' || node.tagName === 'EM');

            const childNodes = [].slice.call(node.childNodes);
            childNodes.forEach(function(node) {
              recursiveMarkupParser(node, textStack, Object.assign({}, { text: node.textContent }, props));
            });
          }
        }

        function getPdfDocTemplate(codes) {
          let dd = {
            pageSize: 'A5',
            pageMargins: [ 30, 30, 30, 30 ],
            content: [],
            styles: {
              headline: {
                fontSize: 12,
                bold: true
              }
            },
            defaultStyle: {
              fontSize: 10,
              lineHeight: 1.2,
              bold: false
            }
          };

          if (typeof corona_test_results.cfg.template_poweredby !== 'undefined' && !!corona_test_results.cfg.template_poweredby && corona_test_results.cfg.template_poweredby !== 'off') {
            dd.background = function(currentPage, pageSize) {
              return {
                fontSize: 7.5,
                alignment: 'center',
                absolutePosition: { x: 0, y: 565 },
                text: parseTextMarkup(corona_test_results.documentTexts.powered_by)
              }
            };
          }

          if (corona_test_results.cfg.template_logoimage) {
            dd.images = {
              logo: corona_test_results.cfg.template_logoimage
            }
          }

          const isSingle = codes.length === 1;

          const borderNone = [ false, false, false, false ];
          const borderBottom = [ false, false, false, true ];

          const surname = prepareTemplateString('{{surname}}', isSingle);
          const firstname = prepareTemplateString('{{firstname}}', isSingle);
          const dateofbirth = prepareTemplateString('{{dateofbirth}}', isSingle);

          const patientDataTable = [];

          patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.documentTexts.labels.testdate + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: isSingle ? getCurrentDisplayDate(isSingle) : lastBatchDisplayDate, border: borderNone } ]);

          if ( requestCert ) {
            const tradename = prepareTemplateString('{{tradename}}', isSingle);
            patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.documentTexts.labels.tradename + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: tradename, border: tradename.length ? borderNone : borderBottom } ]);
          }

          patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.documentTexts.labels.surname + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: surname, border: surname.length ? borderNone : borderBottom } ]);
          patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.documentTexts.labels.firstname + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: firstname, border: firstname.length ? borderNone : borderBottom } ]);
          patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.documentTexts.labels.dateofbirth + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: dateofbirth, border: dateofbirth.length ? borderNone : borderBottom } ]);

          for ( var i = 1; i <= customFieldCount; i++ ) {
            const cfName = 'customfield' + ( i > 1 ? i.toString() : '');
            if ( typeof corona_test_results.cfg[ 'template_' + cfName ] !== 'undefined' && corona_test_results.cfg[ 'template_' + cfName ].length) {
              const customfield = prepareTemplateString('{{' + cfName + '}}', isSingle);
              patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.cfg[ 'template_' + cfName ] + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: customfield, border: customfield.length ? borderNone : borderBottom } ]);
            }
          }

          if ( requestCert ) {
            const cert_email = prepareTemplateString('{{cert_email}}', isSingle);
            const cert_phone = prepareTemplateString('{{cert_phone}}', isSingle);
            const cert_address = prepareTemplateString('{{cert_address}}', isSingle);
            const cert_passport = prepareTemplateString('{{cert_passport}}', isSingle);

            patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.documentTexts.labels.email + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: cert_email, border: cert_email.length ? borderNone : borderBottom } ]);
            patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.certificateTexts.labels.phone + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: cert_phone, border: cert_phone.length ? borderNone : borderBottom } ]);
            patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.certificateTexts.labels.address + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: cert_address, border: cert_address.length ? borderNone : borderBottom } ]);

            if ( ! isSingle || ! cert_address.length ) {
              patientDataTable.push([ {font: 'RobotoBold', text: '', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: ' ', border: borderBottom } ]);
            }

            patientDataTable.push([ {font: 'RobotoBold', text: corona_test_results.certificateTexts.labels.passport + ':', border: borderNone, margin: [ 0, 0, 10, 0 ] }, { text: cert_passport, border: cert_passport.length ? borderNone : borderBottom } ]);
          }

          const tb_topleft = prepareTemplateString(corona_test_results.cfg.template_tb_topleft, isSingle);
          const tb_topright = parseTextMarkup( prepareTemplateString(corona_test_results.cfg.template_tb_topright, isSingle) );
          const tb_before = parseTextMarkup( prepareTemplateString(corona_test_results.cfg.template_tb_before, isSingle) );
          const tb_after = parseTextMarkup( prepareTemplateString(corona_test_results.cfg.template_tb_after, isSingle) );
          const tb_bottom = parseTextMarkup( prepareTemplateString(corona_test_results.cfg[requestCert && requestCert.checked ? 'template_tb_bottom_cert' : 'template_tb_bottom'], isSingle) );
          const tb_bottom_page2 = parseTextMarkup( prepareTemplateString( corona_test_results.cfg?.template_tb_bottom_page2 ) );

          const smallerPage2FontSize = !!corona_test_results.cfg?.template_tb_bottom_page2?.length;
          const tb_bottom_page2_isEmpty = !corona_test_results.cfg?.template_tb_bottom_page2?.trim()?.length;

          codes.forEach(function(codeData, index) {
            let qrCodeSection = {};

            const localQrCode = {
              margin: [ 0, 5, 0, 0 ],
              border: [ false, false, false, false ],
              stack: [
                {
                  fontSize: 14,
                  alignment: 'center',
                  text: [
                    {
                      bold: true,
                      text: corona_test_results.columnHeaders[0] + ': '
                    },
                    {
                      font: 'RobotoMono',
                      characterSpacing: 3,
                      text: codeData.code ? codeData.code : codeData
                    }
                  ]
                },
                {
                  qr: corona_test_results.resultsBaseUrl + '#' + ( codeData.code ? codeData.code : codeData ),
                  alignment: 'center',
                  fit: 90,
                  margin: [ 0, 5, 0, 5 ]
                },
                {
                  fontSize: 14,
                  alignment: 'center',
                  text: [
                    {
                      bold: true,
                      text: codeData.pin ? corona_test_results.documentTexts.pin + ': ' : null
                    },
                    {
                      font: 'RobotoMono',
                      characterSpacing: 3,
                      text: codeData.pin
                    }
                  ]
                }
              ]
            };

            qrCodeSection = localQrCode;

            const logoImage = {};
            if (corona_test_results.cfg.template_logoimage && corona_test_results.cfg.template_logoimage.length) {
              logoImage.image = 'logo';
              logoImage.fit = [ 120, 80 ];
              logoImage.margin = [0, 0, 0, 5];
            }

            if (index > 0) {
              dd.content.push({
                pageBreak: 'before',
                text: null
              });
            }

            const topleft_data_fontsize = ( codeData.code ? codeData.code : codeData ).length <= 10 ? 16 : 14;
            const topleft_data = [{
              fontSize: topleft_data_fontsize,
              alignment: 'left',
              text: [
                {
                  bold: true,
                  text: corona_test_results.columnHeaders[0] + ': '
                },
                {
                  font: 'RobotoMono',
                  characterSpacing: 3,
                  text: codeData.code ? codeData.code : codeData
                }
              ]
            }]

            if ( requestCert && ! pinsDisabled ) {
              topleft_data.push({
                fontSize: topleft_data_fontsize,
                alignment: 'left',
                text: [
                  {
                    bold: true,
                    text: corona_test_results.documentTexts.pin + ': '
                  },
                  {
                    font: 'RobotoMono',
                    characterSpacing: 1,
                    text: codeData.pin
                  }
                ]
              })
            }

            dd.content.push({
              table: {
                widths: ['*', 120],
                body: [
                  [
                    tb_topleft,
                    logoImage
                  ],
                ]
              },
              layout: {
                paddingTop: function (index, node) {
                    applyVerticalAlignment(node, index, ['bottom', 'top']);
                    return 0;
                },
                hLineWidth: function(i) {
                  return 0;
                },
                vLineWidth: function(i) {
                  return 0;
                },
                paddingLeft: function(i) {
                  return i && 4 || 0;
                },
                paddingRight: function(i, node) {
                  return (i < node.table.widths.length - 1) ? 4 : 0;
                }
              }
            },
            {
              table: {
                widths: ['*', 120],
                body: [
                  [ { text: [ '\n', corona_test_results.cfg.template_tb_salutation ] }, JSON.parse(JSON.stringify(tb_topright)) ],
                ]
              },
              layout: {
                paddingTop: function (index, node) {
                  applyVerticalAlignment(node, index, ['bottom', 'bottom']);
                  return 0;
                },
                hLineWidth: function(i) {
                  return 0;
                },
                vLineWidth: function(i) {
                  return 0;
                },
                paddingLeft: function(i) {
                  return i && 4 || 0;
                },
                paddingRight: function(i, node) {
                  return (i < node.table.widths.length - 1) ? 4 : 0;
                }
              }
            },
            {
              margin: [0, 15, 0, 0],
              text: tb_before
            },
            {
                text: corona_test_results.resultsBaseUrl,
                bold: true,
                alignment: 'center',
                margin: [0, 5, 0, 5]
            },
            {
              text: tb_after
            },
            qrCodeSection,
            {
              margin: [0, 5, 0, 0],
              text: tb_bottom,
            },
            {
              pageBreak: 'before',
              text: null
            },
            {
              table: {
                widths: ['*', 120],
                body: [
                  [
                    topleft_data,
                    logoImage
                  ],
                ]
              },
              layout: {
                paddingTop: function (index, node) {
                  applyVerticalAlignment(node, index, ['top', 'top']);
                  return 0;
                },
                hLineWidth: function(i) {
                  return 0;
                },
                vLineWidth: function(i) {
                  return 0;
                },
                paddingLeft: function(i) {
                  return i && 4 || 0;
                },
                paddingRight: function(i, node) {
                  return (i < node.table.widths.length - 1) ? 4 : 0;
                }
              }
            },
            {
              table: {
                widths: ['*', 120],
                body: [
                  [ { style: 'headline', text: corona_test_results.documentTexts.onsite_notice }, JSON.parse(JSON.stringify(tb_topright))],
                ]
              },
              layout: {
                paddingTop: function (index, node) {
                  applyVerticalAlignment(node, index, ['bottom', 'bottom']);
                  return 0;
                },
                hLineWidth: function(i) {
                  return 0;
                },
                vLineWidth: function(i) {
                  return 0;
                },
                paddingLeft: function(i) {
                  return i && 4 || 0;
                },
                paddingRight: function(i, node) {
                  return (i < node.table.widths.length - 1) ? 4 : 0;
                }
              }
            },
            {
              margin: [ 0, 15 ],
              fontSize: smallerPage2FontSize ? 9 : 14,
              lineHeight: smallerPage2FontSize ? 1 : 1.1,
              table: {
                widths: ['auto', '*'],
                body: JSON.parse(JSON.stringify(patientDataTable))
              },
              layout: {
                hLineColor: 'gray',
                paddingTop: function (index, node) {
                  applyVerticalAlignment(node, index, ['top', 'top']);
                  return 5;
                },
                hLineWidth: function(i, node) {
                  return 1;
                },
                vLineWidth: function(i) {
                  return 0;
                },
                paddingLeft: function(i) {
                  return i && 5 || 0;
                },
                paddingRight: function(i, node) {
                  return (i < node.table.widths.length - 1) ? 5 : 0;
                }
              }
            },
            tb_bottom_page2_isEmpty ? {} : {
              margin: [0, 5, 0, 0],
              fontSize: 9,
              text: tb_bottom_page2,
            });
          });

          if (
            !!corona_test_results._customize &&
            !!corona_test_results._customize.document &&
            typeof corona_test_results._customize.document.dd === 'function'
          ) {
            dd = corona_test_results._customize.document.dd( dd, {
              prepareTemplateString: prepareTemplateString,
              isSingle,
              codes,
              codeField,
              pinField,
              requestCert,
              pinsDisabled,
              integrationQrEnabled,
              patientDataTable
            } ) || dd;
          }

          return dd;
        }

        function generatePDF(codes) {
          disableButtons();
          if (!codeField.value && typeof codes === 'undefined') {
            console.error('Cannot generate PDF: Code has not been generated yet.');
            return;
          }

          if ( typeof codes === 'undefined' ) {
            let codeData = { code: codeField.value };
            if ( pinField ) {
              codeData.pin = pinField.value;
            }
            codes = [ codeData ];
          }

          var docDefinition = getPdfDocTemplate(codes);

          const callback = function() {
            enableButtons();
            if (codes.length > 1) {
              batchExportPdfBtn.disabled = false;
            } else {
              switchButtons();
            }
          };

          const fileName = (codes.length === 1 ? codeField.value : 'corona-test-results_Batch') + '.pdf';

          if (isIE11 || codes.length > 1) {
            if ( is_iOS ) {
              handleFileDownload(docDefinition, fileName, callback);
            } else {
              pdfMake.createPdf(docDefinition).download(fileName).then(callback);
            }
          } else {
            const pdfWindow = window.open('');
            if (!pdfWindow) {
              // if the window has been blocked by a popup blocker, try to fall back to downloading
              handleFileDownload(docDefinition, fileName, callback);
            } else {
              pdfMake.createPdf(docDefinition).open(pdfWindow).then(callback);
            }
          }
        }

        resetForm();
        if (debug === true) {
          surname.value = 'Mustermann';
          firstname.value = 'Maximilian';
          dateofbirth.value = '1970-01-01';
        }

        if ( requestCert ) {
          requestCert.addEventListener( 'change', function() {
            let newVal = this.checked ? '' : 'none';
            let certRows = document.querySelectorAll('tr.certificate');
            // for IE11...
            certRows = [].slice.call(certRows);
            certRows.forEach(function(row) {
              row.style.display = newVal;
            });
          });
        }

        const generateOrRegenerate = function() {
          disableButtons();
          const isExistingCode = manualCodes ? !!codeBefore : !!codeField.value;

          // add and get a new code via Ajax
          if (!isExistingCode || requestCert) {
            const isUpdate = requestCert && isExistingCode;
            const ajaxAction = isUpdate ? 'update_codedata' : 'getcode';
            const data = {
              action: 'corona_test_results_ajax_' + ajaxAction,
              _wpnonce: isUpdate ? codeUpdateNonce : corona_test_results.nonces.getcode
            };

            if ( !! pinsBday ) {
              data.bday = dateofbirth.value;
            }

            if ( requestCert && requestCert.checked ) {
              data.certificate_data = {
                tradename: tradename.value,
                surname: surname.value,
                firstname: firstname.value,
                dateofbirth: dateofbirth.value,
                email: cert_email.value,
                phone: cert_phone.value,
                address: cert_address.value,
                passport: cert_passport.value,
              };

              for ( var i = 1; i <= customFieldCount; i++ ) {
                const cfName = 'customfield' + ( i > 1 ? i.toString() : '');
                const cField = document.getElementById(cfName);
                if ( cField ) {
                  data.certificate_data[cfName] = document.getElementById(cfName).value;
                }
              }
            }

            if ( codeField.value ) {
              data.code = codeField.value;
              data.pin = pinField.value;
            }

            if ( manualCodes && isExistingCode && !!codeBefore && codeBefore !== codeField.value ) {
              data.code = codeBefore;
              data.code_update = codeField.value;
            }

            $.ajax({
              type : "post",
              dataType : "json",
              url: ajaxurl,
              data: data,
              success: function(response, xhr) {
                if ( response.success ) {
                  message.style.display = 'none';
                  if ( !isExistingCode ) {
                    codeField.value = response.data.test_result_code;
                  }

                  if ( pinField && typeof response.data.test_result_pin !== 'undefined' ) {
                    pinField.value = response.data.test_result_pin;
                  }

                  if ( !!data.code_update ) {
                    codeBefore = data.code_update;
                  } else if ( !!response.data.test_result_code ) {
                    codeBefore = response.data.test_result_code;
                  }

                  if ( !!response.data.update_nonce ) {
                    codeUpdateNonce = response.data.update_nonce;
                  }

                  if ( !!response.data.created_at ) {
                    codeCreatedAt = response.data.created_at;
                  }

                  if ( response.data.datatransfer && response.data.datatransfer.cwa ) {
                    dataTransferResponses.cwa = response.data.datatransfer.cwa;
                  } else {
                    delete dataTransferResponses.cwa;
                  }
                  generatePDF();
                } else {
                  handleAjaxErrors(xhr);
                }
              },
              error: function(response) {
                handleAjaxErrors(response);
              }
            });
          } else {
            generatePDF();
          }
        };

        submitBtn.addEventListener('click', generateOrRegenerate);
        generateBtn.addEventListener('click', generateOrRegenerate);

        resetBtn.addEventListener('click', function() {
          document.documentElement.scrollIntoView({behavior: 'smooth'});
          resetForm();
        });

        const batchBtn = document.getElementById('generate-batch');
        const batchExportCsvBtn = document.getElementById('batch-export-csv');
        const batchExportPdfBtn = document.getElementById('batch-export-pdf');
        const batchActions = document.getElementById('batch-actions');
        const batchCountInput = document.getElementById('batch-count');
        let lastBatch = {
          codes: []
        };
        let lastBatchDisplayDate = null;
        const dateOverride = document.getElementById('batch-date-override');

        dateOverride && dateOverride.addEventListener('blur', function() {
          if (!batchBtn.classList.contains('loading')) {
            batchBtn.disabled = !batchDisplayDateIsValid();
          }
        });

        const qrBtn = document.getElementById('qrscanner');

        batchBtn.addEventListener('click', function() {
          alert(corona_test_results.premiumFeatureNotice);
        });
        qrBtn.addEventListener('click', function(ev) {
          alert(corona_test_results.premiumFeatureNotice);
        });
        printlabelBtn.addEventListener('click', function() {
          alert(corona_test_results.premiumFeatureNotice);
        });

        corona_test_results._customize._fn = {
          getPdfDocTemplate: getPdfDocTemplate
        };

        break;
    }
  });
})(jQuery);
