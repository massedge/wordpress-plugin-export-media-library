// Check if RML is ready, just to be sure
if (window.rml) {
    
    /* global rml React ReactAIOT jQuery ajaxurl rmlOpts */
    
    var Menu = ReactAIOT.Menu,
        Item = Menu.Item,
        ItemGroup = Menu.ItemGroup,
        IS_DARKMODE = jQuery('body').hasClass('aiot-wp-dark-mode'),
        SUPPORTS_SUBFOLDERS = +rmlOpts.childrenSql > 1,
        massedgeOpts = rmlOpts.massedge_wp_export,
        h = React.createElement;
    
    /**
     * Handle click on a menu item so the correct admin page can be called.
     */
    var handleClick = function(e) {
        var url = rml.uri(ajaxurl).query({
            action: massedgeOpts.action,
            _ajax_nonce: massedgeOpts.nonce,
            type: e.key,
            folder: this.props.store.selectedId
        }).build();
        window.location.href = url;
    };
    
    /**
     * A function which is called to render the dropdown menu.
     */
    var renderMenu = function() {
        var store = this.props.store,
            selected = store.selected,
            isFolder = selected && selected.id > 0;
        
        if (!isFolder) {
            return h(Menu, {
                style: {
                    visibility: 'hidden'
                }
            });
        }
        
        return h(Menu, {
            onClick: handleClick.bind(this),
            theme: IS_DARKMODE ? 'dark' : 'light'
        }, [
            h(ItemGroup, { key: 'wos', title: 'Without RML subfolders' }, [
                h(Item, { key: 'wosFlat' }, 'As flat .zip file'),
                h(Item, { key: 'wosHierarchical' }, 'As hierarchical .zip file (physical structure)')
            ]),
            h(ItemGroup, { key: 'ws', title: ('Include RML subfolders' + (SUPPORTS_SUBFOLDERS ? '' : ' (not supported by your system)')) }, [
                h(Item, { key: 'wsFlat', disabled: !SUPPORTS_SUBFOLDERS }, 'As flat .zip file'),
                h(Item, { key: 'wsHierarchicalRML', disabled: !SUPPORTS_SUBFOLDERS }, 'As hierarchical .zip file (RML structure)'),
                h(Item, { key: 'wsHierarchical', disabled: !SUPPORTS_SUBFOLDERS }, 'As hierarchical .zip file (physical structure)')
            ])
        ]);
    };
    
    // Create download icon and register it to the toolbar
    rml.hooks.register('tree/init', function(state, props) {
        this.stateRefs.ICON_DOWNLOAD_ZIP = h('span', {
            'className': 'dashicons dashicons-download'
        });
        
        this.stateRefs.renderDownloadZipMenu = renderMenu.bind(this);
        
        this.state.toolbar_download_zip = {
            content: 'ICON_DOWNLOAD_ZIP',
            toolTipTitle: 'Download folder as zip',
            toolTipText: 'A folder can be downloaded as flat or hierarchical zip.',
            menu: 'resolve.renderDownloadZipMenu',
            toolTipPlacement: 'topLeft',
            dropdownPlacement: 'bottomLeft'
        };
        
        this.state.availableToolbarButtons.unshift('download_zip');
    });
}