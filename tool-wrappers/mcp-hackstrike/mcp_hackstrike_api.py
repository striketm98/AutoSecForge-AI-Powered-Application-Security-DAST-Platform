from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route('/jsonrpc', methods=['POST'])
def rpc():
    return jsonify({"jsonrpc": "2.0", "result": "ok"})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=6300)
