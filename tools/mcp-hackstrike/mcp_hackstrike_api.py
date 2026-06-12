from flask import Flask; app = Flask(__name__); @app.route("/jsonrpc", methods=["POST"]) def rpc(): return {"result":"ok"}; app.run(host="0.0.0.0", port=6300)
