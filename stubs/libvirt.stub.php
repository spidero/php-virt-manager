<?php

// Stubs for static analysis, generated from the libvirt-php extension 0.5.8 via reflection.

const VIR_DOMAIN_XML_SECURE = 1;
const VIR_DOMAIN_XML_INACTIVE = 2;
const VIR_DOMAIN_XML_UPDATE_CPU = 4;
const VIR_DOMAIN_XML_MIGRATABLE = 8;
const VIR_NODE_CPU_STATS_ALL_CPUS = -1;
const VIR_DOMAIN_NOSTATE = 0;
const VIR_DOMAIN_RUNNING = 1;
const VIR_DOMAIN_BLOCKED = 2;
const VIR_DOMAIN_PAUSED = 3;
const VIR_DOMAIN_SHUTDOWN = 4;
const VIR_DOMAIN_SHUTOFF = 5;
const VIR_DOMAIN_CRASHED = 6;
const VIR_DOMAIN_PMSUSPENDED = 7;
const VIR_STORAGE_VOL_RESIZE_ALLOCATE = 1;
const VIR_STORAGE_VOL_RESIZE_DELTA = 2;
const VIR_STORAGE_VOL_RESIZE_SHRINK = 4;
const VIR_STORAGE_VOL_DELETE_NORMAL = 0;
const VIR_STORAGE_VOL_DELETE_ZEROED = 1;
const VIR_STORAGE_VOL_DELETE_WITH_SNAPSHOTS = 2;
const VIR_STORAGE_VOL_CREATE_PREALLOC_METADATA = 1;
const VIR_STORAGE_VOL_CREATE_REFLINK = 2;
const VIR_DOMAIN_VCPU_CONFIG = 2;
const VIR_DOMAIN_VCPU_CURRENT = 0;
const VIR_DOMAIN_VCPU_LIVE = 1;
const VIR_DOMAIN_VCPU_MAXIMUM = 4;
const VIR_DOMAIN_VCPU_GUEST = 8;
const VIR_SNAPSHOT_DELETE_CHILDREN = 1;
const VIR_SNAPSHOT_DELETE_METADATA_ONLY = 2;
const VIR_SNAPSHOT_DELETE_CHILDREN_ONLY = 4;
const VIR_SNAPSHOT_CREATE_REDEFINE = 1;
const VIR_SNAPSHOT_CREATE_CURRENT = 2;
const VIR_SNAPSHOT_CREATE_NO_METADATA = 4;
const VIR_SNAPSHOT_CREATE_HALT = 8;
const VIR_SNAPSHOT_CREATE_DISK_ONLY = 16;
const VIR_SNAPSHOT_CREATE_REUSE_EXT = 32;
const VIR_SNAPSHOT_CREATE_QUIESCE = 64;
const VIR_SNAPSHOT_CREATE_ATOMIC = 128;
const VIR_SNAPSHOT_CREATE_LIVE = 256;
const VIR_SNAPSHOT_LIST_DESCENDANTS = 1;
const VIR_SNAPSHOT_LIST_ROOTS = 1;
const VIR_SNAPSHOT_LIST_METADATA = 2;
const VIR_SNAPSHOT_LIST_LEAVES = 4;
const VIR_SNAPSHOT_LIST_NO_LEAVES = 8;
const VIR_SNAPSHOT_LIST_NO_METADATA = 16;
const VIR_SNAPSHOT_LIST_INACTIVE = 32;
const VIR_SNAPSHOT_LIST_ACTIVE = 64;
const VIR_SNAPSHOT_LIST_DISK_ONLY = 128;
const VIR_SNAPSHOT_LIST_INTERNAL = 256;
const VIR_SNAPSHOT_LIST_EXTERNAL = 512;
const VIR_SNAPSHOT_REVERT_RUNNING = 1;
const VIR_SNAPSHOT_REVERT_PAUSED = 2;
const VIR_SNAPSHOT_REVERT_FORCE = 4;
const VIR_DOMAIN_NONE = 0;
const VIR_DOMAIN_START_PAUSED = 1;
const VIR_DOMAIN_START_AUTODESTROY = 2;
const VIR_DOMAIN_START_BYPASS_CACHE = 4;
const VIR_DOMAIN_START_FORCE_BOOT = 8;
const VIR_DOMAIN_START_VALIDATE = 16;
const VIR_MEMORY_VIRTUAL = 1;
const VIR_MEMORY_PHYSICAL = 2;
const VIR_VERSION_BINDING = 1;
const VIR_VERSION_LIBVIRT = 2;
const VIR_NETWORKS_ACTIVE = 1;
const VIR_NETWORKS_INACTIVE = 2;
const VIR_NETWORKS_ALL = 3;
const VIR_CONNECT_LIST_NETWORKS_INACTIVE = 1;
const VIR_CONNECT_LIST_NETWORKS_ACTIVE = 2;
const VIR_CONNECT_LIST_NETWORKS_PERSISTENT = 4;
const VIR_CONNECT_LIST_NETWORKS_TRANSIENT = 8;
const VIR_CONNECT_LIST_NETWORKS_AUTOSTART = 16;
const VIR_CONNECT_LIST_NETWORKS_NO_AUTOSTART = 32;
const VIR_CRED_USERNAME = 1;
const VIR_CRED_AUTHNAME = 2;
const VIR_CRED_LANGUAGE = 3;
const VIR_CRED_CNONCE = 4;
const VIR_CRED_PASSPHRASE = 5;
const VIR_CRED_ECHOPROMPT = 6;
const VIR_CRED_NOECHOPROMPT = 7;
const VIR_CRED_REALM = 8;
const VIR_CRED_EXTERNAL = 9;
const VIR_DOMAIN_MEMORY_STAT_SWAP_IN = 0;
const VIR_DOMAIN_MEMORY_STAT_SWAP_OUT = 1;
const VIR_DOMAIN_MEMORY_STAT_MAJOR_FAULT = 2;
const VIR_DOMAIN_MEMORY_STAT_MINOR_FAULT = 3;
const VIR_DOMAIN_MEMORY_STAT_UNUSED = 4;
const VIR_DOMAIN_MEMORY_STAT_AVAILABLE = 5;
const VIR_DOMAIN_MEMORY_STAT_ACTUAL_BALLOON = 6;
const VIR_DOMAIN_MEMORY_STAT_RSS = 7;
const VIR_DOMAIN_MEMORY_STAT_NR = 8;
const VIR_DOMAIN_JOB_NONE = 0;
const VIR_DOMAIN_JOB_BOUNDED = 1;
const VIR_DOMAIN_JOB_UNBOUNDED = 2;
const VIR_DOMAIN_JOB_COMPLETED = 3;
const VIR_DOMAIN_JOB_FAILED = 4;
const VIR_DOMAIN_JOB_CANCELLED = 5;
const VIR_DOMAIN_BLOCK_COMMIT_SHALLOW = 1;
const VIR_DOMAIN_BLOCK_COMMIT_DELETE = 2;
const VIR_DOMAIN_BLOCK_COMMIT_ACTIVE = 4;
const VIR_DOMAIN_BLOCK_COMMIT_RELATIVE = 8;
const VIR_DOMAIN_BLOCK_COMMIT_BANDWIDTH_BYTES = 16;
const VIR_DOMAIN_BLOCK_COPY_SHALLOW = 1;
const VIR_DOMAIN_BLOCK_COPY_REUSE_EXT = 2;
const VIR_DOMAIN_BLOCK_JOB_ABORT_ASYNC = 1;
const VIR_DOMAIN_BLOCK_JOB_ABORT_PIVOT = 2;
const VIR_DOMAIN_BLOCK_JOB_SPEED_BANDWIDTH_BYTES = 1;
const VIR_DOMAIN_BLOCK_JOB_INFO_BANDWIDTH_BYTES = 1;
const VIR_DOMAIN_BLOCK_JOB_TYPE_UNKNOWN = 0;
const VIR_DOMAIN_BLOCK_JOB_TYPE_PULL = 1;
const VIR_DOMAIN_BLOCK_JOB_TYPE_COPY = 2;
const VIR_DOMAIN_BLOCK_JOB_TYPE_COMMIT = 3;
const VIR_DOMAIN_BLOCK_JOB_TYPE_ACTIVE_COMMIT = 4;
const VIR_DOMAIN_BLOCK_PULL_BANDWIDTH_BYTES = 64;
const VIR_DOMAIN_BLOCK_REBASE_SHALLOW = 1;
const VIR_DOMAIN_BLOCK_REBASE_REUSE_EXT = 2;
const VIR_DOMAIN_BLOCK_REBASE_COPY_RAW = 4;
const VIR_DOMAIN_BLOCK_REBASE_COPY = 8;
const VIR_DOMAIN_BLOCK_REBASE_RELATIVE = 16;
const VIR_DOMAIN_BLOCK_REBASE_COPY_DEV = 32;
const VIR_DOMAIN_BLOCK_REBASE_BANDWIDTH_BYTES = 64;
const VIR_DOMAIN_BLOCK_RESIZE_BYTES = 1;
const VIR_DOMAIN_BLOCK_COPY_BANDWIDTH = 'bandwidth';
const VIR_DOMAIN_BLOCK_COPY_GRANULARITY = 'granularity';
const VIR_DOMAIN_BLOCK_COPY_BUF_SIZE = 'buf-size';
const VIR_MIGRATE_LIVE = 1;
const VIR_MIGRATE_PEER2PEER = 2;
const VIR_MIGRATE_TUNNELLED = 4;
const VIR_MIGRATE_PERSIST_DEST = 8;
const VIR_MIGRATE_UNDEFINE_SOURCE = 16;
const VIR_MIGRATE_PAUSED = 32;
const VIR_MIGRATE_NON_SHARED_DISK = 64;
const VIR_MIGRATE_NON_SHARED_INC = 128;
const VIR_MIGRATE_CHANGE_PROTECTION = 256;
const VIR_MIGRATE_UNSAFE = 512;
const VIR_MIGRATE_OFFLINE = 1024;
const VIR_MIGRATE_COMPRESSED = 2048;
const VIR_MIGRATE_ABORT_ON_ERROR = 4096;
const VIR_MIGRATE_AUTO_CONVERGE = 8192;
const VIR_DOMAIN_DEVICE_MODIFY_CURRENT = 0;
const VIR_DOMAIN_DEVICE_MODIFY_LIVE = 1;
const VIR_DOMAIN_DEVICE_MODIFY_CONFIG = 2;
const VIR_DOMAIN_DEVICE_MODIFY_FORCE = 4;
const VIR_STORAGE_POOL_BUILD_NEW = 0;
const VIR_STORAGE_POOL_BUILD_REPAIR = 1;
const VIR_STORAGE_POOL_BUILD_RESIZE = 2;
const VIR_DOMAIN_FLAG_FEATURE_ACPI = 1;
const VIR_DOMAIN_FLAG_FEATURE_APIC = 2;
const VIR_DOMAIN_FLAG_FEATURE_PAE = 4;
const VIR_DOMAIN_FLAG_CLOCK_LOCALTIME = 8;
const VIR_DOMAIN_FLAG_TEST_LOCAL_VNC = 16;
const VIR_DOMAIN_FLAG_SOUND_AC97 = 32;
const VIR_DOMAIN_DISK_FILE = 1;
const VIR_DOMAIN_DISK_BLOCK = 2;
const VIR_DOMAIN_DISK_ACCESS_ALL = 4;
const VIR_DOMAIN_METADATA_DESCRIPTION = 0;
const VIR_DOMAIN_METADATA_TITLE = 1;
const VIR_DOMAIN_METADATA_ELEMENT = 2;
const VIR_DOMAIN_AFFECT_CURRENT = 0;
const VIR_DOMAIN_AFFECT_LIVE = 1;
const VIR_DOMAIN_AFFECT_CONFIG = 2;
const VIR_DOMAIN_STATS_STATE = 1;
const VIR_DOMAIN_STATS_CPU_TOTAL = 2;
const VIR_DOMAIN_STATS_BALLOON = 4;
const VIR_DOMAIN_STATS_VCPU = 8;
const VIR_DOMAIN_STATS_INTERFACE = 16;
const VIR_DOMAIN_STATS_BLOCK = 32;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_ACTIVE = 1;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_INACTIVE = 2;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_OTHER = 128;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_PAUSED = 32;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_PERSISTENT = 4;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_RUNNING = 16;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_SHUTOFF = 64;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_TRANSIENT = 8;
const VIR_CONNECT_GET_ALL_DOMAINS_STATS_ENFORCE_STATS = 2147483648;
const VIR_DOMAIN_MEM_CONFIG = 2;
const VIR_DOMAIN_MEM_CURRENT = 0;
const VIR_DOMAIN_MEM_LIVE = 1;
const VIR_DOMAIN_MEM_MAXIMUM = 4;
const VIR_DOMAIN_INTERFACE_ADDRESSES_SRC_LEASE = 0;
const VIR_DOMAIN_INTERFACE_ADDRESSES_SRC_AGENT = 1;
const VIR_DOMAIN_INTERFACE_ADDRESSES_SRC_ARP = 0;
const VIR_CONNECT_FLAG_SOUNDHW_GET_NAMES = 1;
const VIR_KEYCODE_SET_LINUX = 0;
const VIR_KEYCODE_SET_XT = 1;
const VIR_KEYCODE_SET_ATSET1 = 2;
const VIR_KEYCODE_SET_ATSET2 = 3;
const VIR_KEYCODE_SET_ATSET3 = 4;
const VIR_KEYCODE_SET_OSX = 5;
const VIR_KEYCODE_SET_XT_KBD = 6;
const VIR_KEYCODE_SET_USB = 7;
const VIR_KEYCODE_SET_WIN32 = 8;
const VIR_KEYCODE_SET_RFB = 9;
const VIR_DOMAIN_UNDEFINE_MANAGED_SAVE = 1;
const VIR_DOMAIN_UNDEFINE_SNAPSHOTS_METADATA = 2;
const VIR_DOMAIN_UNDEFINE_NVRAM = 4;
const VIR_DOMAIN_UNDEFINE_KEEP_NVRAM = 8;
const VIR_DOMAIN_UNDEFINE_CHECKPOINTS_METADATA = 16;
const VIR_DOMAIN_UNDEFINE_TPM = 32;
const VIR_DOMAIN_UNDEFINE_KEEP_TPM = 64;

/** @return mixed */
function libvirt_connect(mixed $url = null, mixed $readonly = null, mixed $credentials = null) {}
/** @return mixed */
function libvirt_connect_get_uri(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_hostname(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_hypervisor(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_capabilities(mixed $conn, mixed $xpath = null) {}
/** @return mixed */
function libvirt_connect_get_domain_capabilities(mixed $conn, mixed $emulatorbin = null, mixed $arch = null, mixed $machine = null, mixed $virttype = null, mixed $flags = null, mixed $xpath = null) {}
/** @return mixed */
function libvirt_connect_get_emulator(mixed $conn, mixed $arch = null) {}
/** @return mixed */
function libvirt_connect_get_nic_models(mixed $conn, mixed $arch = null) {}
/** @return mixed */
function libvirt_connect_get_soundhw_models(mixed $conn, mixed $arch = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_connect_get_maxvcpus(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_sysinfo(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_encrypted(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_secure(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_information(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_machine_types(mixed $conn) {}
/** @return mixed */
function libvirt_connect_get_all_domain_stats(mixed $conn, mixed $stats = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_stream_create(mixed $conn) {}
/** @return mixed */
function libvirt_stream_close(mixed $conn) {}
/** @return mixed */
function libvirt_stream_abort(mixed $conn) {}
/** @return mixed */
function libvirt_stream_finish(mixed $conn) {}
/** @return mixed */
function libvirt_stream_send(mixed $conn, mixed $data, mixed $len = null) {}
/** @return mixed */
function libvirt_stream_recv(mixed $conn, mixed $data, mixed $len = null) {}
/** @return mixed */
function libvirt_domain_new(mixed $conn, mixed $name, mixed $arch, mixed $memMB, mixed $maxmemMB, mixed $vcpus, mixed $iso, mixed $disks, mixed $networks, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_new_get_vnc() {}
/** @return mixed */
function libvirt_domain_get_counts(mixed $conn) {}
/** @return mixed */
function libvirt_domain_is_persistent(mixed $conn) {}
/** @return mixed */
function libvirt_domain_lookup_by_name(mixed $conn, mixed $name) {}
/** @return mixed */
function libvirt_domain_get_xml_desc(mixed $res, mixed $xpath = null, mixed $flags = null) {} // flags param missing in extension arginfo
/** @return mixed */
function libvirt_domain_get_disk_devices(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_interface_devices(mixed $conn) {}
/** @return mixed */
function libvirt_domain_change_vcpus(mixed $conn, mixed $numCpus, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_change_memory(mixed $conn, mixed $allocMem, mixed $allocMax, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_change_boot_devices(mixed $conn, mixed $first, mixed $second, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_disk_add(mixed $conn, mixed $img, mixed $dev, mixed $type, mixed $driver, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_disk_remove(mixed $conn, mixed $dev, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_nic_add(mixed $conn, mixed $mac, mixed $network, mixed $model, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_nic_remove(mixed $conn, mixed $dev, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_attach_device(mixed $conn, mixed $xml, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_detach_device(mixed $conn, mixed $xml, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_get_info(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_name(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_uuid(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_uuid_string(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_id(mixed $conn) {}
/** @return mixed */
function libvirt_domain_lookup_by_uuid(mixed $conn, mixed $uuid) {}
/** @return mixed */
function libvirt_domain_lookup_by_uuid_string(mixed $conn, mixed $uuid) {}
/** @return mixed */
function libvirt_domain_lookup_by_id(mixed $conn, mixed $id) {}
/** @return mixed */
function libvirt_domain_create(mixed $conn) {}
/** @return mixed */
function libvirt_domain_destroy(mixed $conn) {}
/** @return mixed */
function libvirt_domain_resume(mixed $conn) {}
/** @return mixed */
function libvirt_domain_core_dump(mixed $conn, mixed $to) {}
/** @return mixed */
function libvirt_domain_shutdown(mixed $conn) {}
/** @return mixed */
function libvirt_domain_suspend(mixed $conn) {}
/** @return mixed */
function libvirt_domain_managedsave(mixed $conn) {}
/** @return mixed */
function libvirt_domain_undefine(mixed $conn) {}
/** @return mixed */
function libvirt_domain_undefine_flags(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_domain_reboot(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_domain_reset(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_domain_define_xml(mixed $conn, mixed $xml) {}
/** @return mixed */
function libvirt_domain_create_xml(mixed $conn, mixed $xml) {}
/** @return mixed */
function libvirt_domain_xml_from_native(mixed $conn, mixed $format, mixed $config_data) {}
/** @return mixed */
function libvirt_domain_xml_to_native(mixed $conn, mixed $format, mixed $xml_data) {}
/** @return mixed */
function libvirt_domain_memory_peek(mixed $conn, mixed $start, mixed $size, mixed $flags) {}
/** @return mixed */
function libvirt_domain_memory_stats(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_domain_set_memory(mixed $conn, mixed $memory) {}
/** @return mixed */
function libvirt_domain_set_max_memory(mixed $conn, mixed $memory) {}
/** @return mixed */
function libvirt_domain_set_memory_flags(mixed $conn, mixed $memory, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_block_commit(mixed $res, mixed $disk, mixed $base = null, mixed $top = null) {}
/** @return mixed */
function libvirt_domain_block_copy(mixed $res, mixed $disk, mixed $destxml, mixed $params = null) {}
/** @return mixed */
function libvirt_domain_block_pull(mixed $res, mixed $disk, mixed $bandwidth = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_block_rebase(mixed $res, mixed $disk, mixed $base, mixed $bandwidth = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_block_stats(mixed $conn, mixed $path) {}
/** @return mixed */
function libvirt_domain_block_resize(mixed $conn, mixed $path, mixed $size, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_block_job_info(mixed $dom, mixed $disk, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_block_job_abort(mixed $conn, mixed $path, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_block_job_set_speed(mixed $conn, mixed $path, mixed $bandwidth, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_interface_addresses(mixed $domain, mixed $source) {}
/** @return mixed */
function libvirt_domain_interface_stats(mixed $conn, mixed $path) {}
/** @return mixed */
function libvirt_domain_get_connect(mixed $conn) {}
/** @return mixed */
function libvirt_domain_migrate(mixed $res, mixed $dest_conn, mixed $flags, mixed $dname = null, mixed $bandwidth = null) {}
/** @return mixed */
function libvirt_domain_migrate_to_uri(mixed $res, mixed $dest_uri, mixed $flags, mixed $dname = null, mixed $bandwidth = null) {}
/** @return mixed */
function libvirt_domain_migrate_to_uri2(mixed $res, mixed $dconn_uri = null, mixed $mig_uri = null, mixed $dxml = null, mixed $flags = null, mixed $dname = null, mixed $bandwidth = null) {}
/** @return mixed */
function libvirt_domain_get_job_info(mixed $conn) {}
/** @return mixed */
function libvirt_domain_xml_xpath(mixed $res, mixed $xpath, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_get_block_info(mixed $res, mixed $dev) {}
/** @return mixed */
function libvirt_domain_get_network_info(mixed $res, mixed $mac) {}
/** @return mixed */
function libvirt_domain_get_autostart(mixed $conn) {}
/** @return mixed */
function libvirt_domain_set_autostart(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_domain_get_metadata(mixed $conn, mixed $type, mixed $uri, mixed $flags) {}
/** @return mixed */
function libvirt_domain_set_metadata(mixed $conn, mixed $type, mixed $metadata, mixed $key, mixed $uri, mixed $flags) {}
/** @return mixed */
function libvirt_domain_is_active(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_next_dev_ids(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_screenshot(mixed $conn, mixed $server, mixed $scancode = null) {}
/** @return mixed */
function libvirt_domain_get_screenshot_api(mixed $conn, mixed $screenID = null) {}
/** @return mixed */
function libvirt_domain_get_screen_dimensions(mixed $conn, mixed $server) {}
/** @return mixed */
function libvirt_domain_send_keys(mixed $conn, mixed $server, mixed $scancode) {}
/** @return mixed */
function libvirt_domain_send_key_api(mixed $conn, mixed $codeset, mixed $holdime, mixed $keycodes, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_send_pointer_event(mixed $conn, mixed $server, mixed $pos_x, mixed $pox_y, mixed $clicked, mixed $release = null) {}
/** @return mixed */
function libvirt_domain_update_device(mixed $conn, mixed $xml, mixed $flags) {}
/** @return mixed */
function libvirt_domain_qemu_agent_command(mixed $conn, mixed $cmd, mixed $timeout = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_list_domains(mixed $conn) {}
/** @return mixed */
function libvirt_list_domain_resources(mixed $conn) {}
/** @return mixed */
function libvirt_list_active_domain_ids(mixed $conn) {}
/** @return mixed */
function libvirt_list_active_domains(mixed $conn) {}
/** @return mixed */
function libvirt_list_inactive_domains(mixed $conn) {}
/** @return mixed */
function libvirt_domain_get_cpu_total_stats(mixed $conn) {}
/** @return mixed */
function libvirt_domain_has_current_snapshot(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_snapshot_lookup_by_name(mixed $conn, mixed $name, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_snapshot_create(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_snapshot_create_xml(mixed $conn, mixed $xml, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_snapshot_current(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_snapshot_get_xml(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_snapshot_revert(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_domain_snapshot_delete(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_list_domain_snapshots(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_storagepool_lookup_by_name(mixed $conn, mixed $name) {}
/** @return mixed */
function libvirt_storagepool_lookup_by_volume(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_list_volumes(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_get_info(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_get_uuid_string(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_get_name(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_lookup_by_uuid_string(mixed $conn, mixed $uuid) {}
/** @return mixed */
function libvirt_storagepool_get_xml_desc(mixed $conn, mixed $xpath = null) {}
/** @return mixed */
function libvirt_storagepool_define_xml(mixed $conn, mixed $xml, mixed $flags = null) {}
/** @return mixed */
function libvirt_storagepool_undefine(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_create(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_destroy(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_is_active(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_get_volume_count(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_refresh(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_storagepool_set_autostart(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_storagepool_get_autostart(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_build(mixed $conn) {}
/** @return mixed */
function libvirt_storagepool_delete(mixed $conn) {}
/** @return mixed */
function libvirt_storagevolume_lookup_by_name(mixed $conn, mixed $name) {}
/** @return mixed */
function libvirt_storagevolume_lookup_by_path(mixed $conn, mixed $path) {}
/** @return mixed */
function libvirt_storagevolume_get_name(mixed $conn) {}
/** @return mixed */
function libvirt_storagevolume_get_path(mixed $conn) {}
/** @return mixed */
function libvirt_storagevolume_get_info(mixed $conn) {}
/** @return mixed */
function libvirt_storagevolume_get_xml_desc(mixed $conn, mixed $xpath, mixed $flags = null) {}
/** @return mixed */
function libvirt_storagevolume_create_xml(mixed $conn, mixed $xml) {}
/** @return mixed */
function libvirt_storagevolume_create_xml_from(mixed $pool, mixed $xml, mixed $original_volume) {}
/** @return mixed */
function libvirt_storagevolume_delete(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_storagevolume_download(mixed $conn, mixed $stream, mixed $offset = null, mixed $length = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_storagevolume_upload(mixed $conn, mixed $stream, mixed $offset = null, mixed $length = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_storagevolume_resize(mixed $conn, mixed $capacity, mixed $flags = null) {}
/** @return mixed */
function libvirt_list_storagepools(mixed $conn) {}
/** @return mixed */
function libvirt_list_active_storagepools(mixed $conn) {}
/** @return mixed */
function libvirt_list_inactive_storagepools(mixed $conn) {}
/** @return mixed */
function libvirt_network_define_xml(mixed $conn, mixed $xml) {}
/** @return mixed */
function libvirt_network_get_xml_desc(mixed $conn, mixed $xpath = null) {}
/** @return mixed */
function libvirt_network_undefine(mixed $conn) {}
/** @return mixed */
function libvirt_network_get(mixed $conn, mixed $name) {}
/** @return mixed */
function libvirt_network_get_active(mixed $conn) {}
/** @return mixed */
function libvirt_network_set_active(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_network_get_bridge(mixed $conn) {}
/** @return mixed */
function libvirt_network_get_information(mixed $conn) {}
/** @return mixed */
function libvirt_network_get_uuid_string(mixed $conn) {}
/** @return mixed */
function libvirt_network_get_uuid(mixed $conn) {}
/** @return mixed */
function libvirt_network_get_name(mixed $conn) {}
/** @return mixed */
function libvirt_network_get_autostart(mixed $conn) {}
/** @return mixed */
function libvirt_network_set_autostart(mixed $conn, mixed $flags) {}
/** @return mixed */
function libvirt_list_all_networks(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_list_networks(mixed $conn, mixed $flags = null) {}
/** @return mixed */
function libvirt_network_get_dhcp_leases(mixed $conn, mixed $mac = null, mixed $flags = null) {}
/** @return mixed */
function libvirt_node_get_info(mixed $conn) {}
/** @return mixed */
function libvirt_node_get_cpu_stats(mixed $conn, mixed $cpunr = null) {}
/** @return mixed */
function libvirt_node_get_cpu_stats_for_each_cpu(mixed $conn, mixed $time = null) {}
/** @return mixed */
function libvirt_node_get_mem_stats(mixed $conn) {}
/** @return mixed */
function libvirt_node_get_free_memory(mixed $conn) {}
/** @return mixed */
function libvirt_nodedev_get(mixed $conn) {}
/** @return mixed */
function libvirt_nodedev_capabilities(mixed $conn) {}
/** @return mixed */
function libvirt_nodedev_get_xml_desc(mixed $conn, mixed $xpath = null) {}
/** @return mixed */
function libvirt_nodedev_get_information(mixed $conn) {}
/** @return mixed */
function libvirt_list_nodedevs(mixed $conn, mixed $cap = null) {}
/** @return mixed */
function libvirt_nwfilter_define_xml(mixed $conn, mixed $xml) {}
/** @return mixed */
function libvirt_nwfilter_undefine(mixed $conn) {}
/** @return mixed */
function libvirt_nwfilter_get_xml_desc(mixed $conn, mixed $xpath = null) {}
/** @return mixed */
function libvirt_nwfilter_get_uuid_string(mixed $conn) {}
/** @return mixed */
function libvirt_nwfilter_get_uuid(mixed $conn) {}
/** @return mixed */
function libvirt_nwfilter_get_name(mixed $conn) {}
/** @return mixed */
function libvirt_nwfilter_lookup_by_name(mixed $conn, mixed $name) {}
/** @return mixed */
function libvirt_nwfilter_lookup_by_uuid_string(mixed $conn, mixed $uuid) {}
/** @return mixed */
function libvirt_nwfilter_lookup_by_uuid(mixed $conn, mixed $uuid) {}
/** @return mixed */
function libvirt_list_all_nwfilters(mixed $conn) {}
/** @return mixed */
function libvirt_list_nwfilters(mixed $conn) {}
/** @return mixed */
function libvirt_get_last_error() {}
/** @return mixed */
function libvirt_version(mixed $type = null) {}
/** @return mixed */
function libvirt_check_version(mixed $major, mixed $minor, mixed $micro, mixed $type = null) {}
/** @return mixed */
function libvirt_has_feature(mixed $name) {}
/** @return mixed */
function libvirt_get_iso_images(mixed $path = null) {}
/** @return mixed */
function libvirt_image_create(mixed $conn, mixed $name, mixed $size, mixed $format) {}
/** @return mixed */
function libvirt_image_remove(mixed $conn, mixed $image) {}
/** @return mixed */
function libvirt_logfile_set(mixed $filename, mixed $maxsize = null) {}
/** @return mixed */
function libvirt_print_binding_resources() {}
